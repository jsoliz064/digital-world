<?php

namespace App\Livewire\TecnicoProducto\Modals;

use App\Enums\ProductoEstado;
use App\Models\Bitacora;
use App\Models\ProductoModelo;
use App\Models\ProductoReparacion;
use App\Models\ProductoReparacionRepuesto;
use App\Models\Repuesto;
use App\Models\RepuestoCategoria;
use App\Models\Sucursal;
use App\Services\EstadoProductoService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;

class ReparacionEditModal extends Component
{
    public $openModal = false;
    public $reparacionModel;
    public $reparacion = [];

    public $repuestos = [];

    /**
     * La sucursal de donde salen las piezas que se agregan en esta edicion.
     *
     * Se CONGELA en cada linea nueva. No se deduce de $producto->sucursal_id, y
     * aqui el motivo se ve a simple vista: unas lineas mas abajo, este mismo
     * metodo reasigna el equipo al Almacen cuando la reparacion se termina. Leer
     * la sucursal del producto en el bucle de stock daria el Almacen en vez de
     * donde el tecnico tiene las piezas.
     */
    public $sucursalRepuestos = null;
    public $repuestosOriginales = [];
    public $repuestosEliminados = [];

    public $searchRepuesto = '';
    public $categoriaId = null;
    public $modeloId = null;
    public $categorias;
    public $modelos;
    public $filteredRepuestos = [];

    public function render()
    {
        return view('livewire.tecnico-producto.modals.reparacion-edit-modal', [
            'sucursales' => Sucursal::activas()->orderBy('nombre')->get(),
        ]);
    }

    #[On('openReparacionEditModal')]
    public function openModal($id)
    {
        $reparacion = ProductoReparacion::find($id);
        $this->categorias = RepuestoCategoria::all();
        $this->modelos = ProductoModelo::all();
        $this->reparacionModel = $reparacion;
        $this->reparacion = $reparacion->toArray();
        $this->reparacion['pagado'] = (bool) $this->reparacion['pagado'];
        $this->reparacion['garantia_tecnico'] = (bool) $this->reparacion['garantia_tecnico'];

        foreach ($reparacion->repuestos as $reparacionRepuesto) {
            $modelo = $reparacionRepuesto->repuesto->modelo;
            $this->repuestos[] = [
                'id' => $reparacionRepuesto->id,
                'repuesto_id' => $reparacionRepuesto->repuesto_id,
                'nombre' => $reparacionRepuesto->repuesto->nombre,
                'modelo' => $modelo ? $modelo->nombre : '',
                'fabricante' => $reparacionRepuesto->repuesto->fabricante,
                'costo' => $reparacionRepuesto->costo,
                'cantidad' => $reparacionRepuesto->cantidad,
                'subtotal_costo' => $reparacionRepuesto->subtotal_costo,
                'subtotal_costo_bs' => $reparacionRepuesto->subtotal_costo_bs,
            ];
        }

        $this->repuestosOriginales = $this->repuestos;
        $this->openModal = true;
    }

    public function updatedCategoriaId()
    {
        if ($this->categoriaId == "") {
            $this->categoriaId = null;
        }
        $this->filterRepuestos($this->searchRepuesto);
    }

    public function updatedModeloId()
    {
        if ($this->modeloId == "") {
            $this->modeloId = null;
        }
        $this->filterRepuestos($this->searchRepuesto);
    }

    public function updatedSearchRepuesto($value)
    {
        if (empty($value)) {
            $this->filteredRepuestos = [];
            return;
        }

        $this->filterRepuestos($value);
    }

    private function filterRepuestos($search)
    {
        $idsExistentes = collect($this->repuestos)->pluck('repuesto_id')->toArray();

        // Un accesorio no se monta en una reparacion: el filtro es
        // incondicional y vive en un scope para que el proximo buscador que
        // alguien escriba no se olvide de el.
        $this->filteredRepuestos = Repuesto::soloRepuestos()->where(function ($query) use ($search) {
            $query->where('nombre', 'like', '%' . $search . '%')
                ->orWhere('fabricante', 'like', '%' . $search . '%')
                ->orWhereHas('modelo', function ($queryModelo) use ($search) {
                    $queryModelo->where('nombre', 'like', '%' . $search . '%');
                });
        })
            ->whereNotIn('id', $idsExistentes)
            ->when($this->categoriaId, function ($query) {
                $query->where('repuesto_categoria_id', $this->categoriaId);
            })
            ->when($this->modeloId, function ($query) {
                $query->where('producto_modelo_id', $this->modeloId);
            })
            ->orderBy('nombre')
            ->take(20)
            ->get();
    }

    public function selectRepuesto($id)
    {
        $repuesto = Repuesto::find($id);
        if (!$repuesto)
            return;

        array_unshift($this->repuestos, [
            'id' => null,
            'repuesto_id' => $repuesto->id,
            'nombre' => $repuesto->nombre,
            'modelo' => $repuesto->modelo ? $repuesto->modelo->nombre : '',
            'fabricante' => $repuesto->fabricante,
            'costo' => $repuesto->costo,
            'cantidad' => 1,
            'subtotal_costo' => $repuesto->costo,
            'subtotal_costo_bs' => $repuesto->costo * $this->reparacion['tipo_cambio'],
        ]);

        $this->searchRepuesto = '';
        $this->filteredRepuestos = [];
        $this->calcularTotalRepuestos();
    }

    public function updatedRepuestos()
    {
        $this->calcularTotalRepuestos();
    }

    public function calcularTotalRepuestos()
    {
        $costoTotalRepuestosBs = 0;

        foreach ($this->repuestos as $index => $repuesto) {
            $costo = is_numeric($repuesto['costo']) ? (float) $repuesto['costo'] : 0;
            $cantidad = is_numeric($repuesto['cantidad']) ? (int) $repuesto['cantidad'] : 0;
            if ($cantidad <= 0) {
                $this->repuestos[$index]['cantidad'] = 1;
                return;
            }

            $subtotalCostoRepuesto = $costo * $cantidad;
            $this->repuestos[$index]['subtotal_costo'] = $subtotalCostoRepuesto;
            $subtotalCostoRepuestoBs = $subtotalCostoRepuesto * $this->reparacion['tipo_cambio'];
            $this->repuestos[$index]['subtotal_costo_bs'] = $subtotalCostoRepuestoBs;
            $costoTotalRepuestosBs += $subtotalCostoRepuestoBs;
        }
        $this->reparacion['costo_repuestos'] = $costoTotalRepuestosBs;
        $this->calcularTotalReparacion();
    }

    public function updatedReparacionCosto()
    {
        $this->calcularTotalReparacion();
    }

    public function updatedReparacionCostoRepuestos()
    {
        $this->calcularTotalReparacion();
    }

    public function updatedReparacionTipoCambio()
    {
        $this->calcularTotalReparacion();
    }

    public function calcularTotalReparacion()
    {
        $costo = floatval($this->reparacion['costo'] ?? 0);
        $costo_repuestos = floatval($this->reparacion['costo_repuestos'] ?? 0);
        $tipo_cambio = floatval($this->reparacion['tipo_cambio'] ?? 0);
        $costo_usd = $tipo_cambio > 0 ? $costo / $tipo_cambio : 0;
        $costo_repuestos_usd = $tipo_cambio > 0 ? $costo_repuestos / $tipo_cambio : 0;
        $costo_total = $costo_usd + $costo_repuestos_usd;
        $this->reparacion['costo_total'] = $costo_total;
        $this->reparacion['costo_total_bs'] = $costo + $costo_repuestos;
    }

    public function eliminarRepuesto($index)
    {
        if (isset($this->repuestos[$index]['id']) && !is_null($this->repuestos[$index]['id'])) {
            $this->repuestosEliminados[] = $this->repuestos[$index]['id'];
        }
        unset($this->repuestos[$index]);
        $this->repuestos = array_values($this->repuestos);
        $this->calcularTotalRepuestos();
    }

    public function update()
    {
        // Fuera de la transaccion: validar no escribe nada, y abrirla para
        // descubrir que falta un campo deja los locks tomados mientras se
        // compone el mensaje de error.
        $this->validate([
            'reparacion.costo' => 'required|numeric|min:0',
            'reparacion.costo_repuestos' => 'required|numeric|min:0',
            'reparacion.tipo_cambio' => 'required|numeric|min:0',
            'reparacion.costo_total' => 'required|numeric|min:0',
            'reparacion.costo_total_bs' => 'required|numeric|min:0',
            'reparacion.fecha_entrega' => 'required',
            'reparacion.garantia_tecnico' => 'required',
            'reparacion.pagado' => 'required',
        ]);

        $stock = new \App\Services\StockRepuestoService();
        $estados = app(EstadoProductoService::class);

        $reparacionGuardada = DB::transaction(function () use ($stock, $estados) {
            $descripcion = "Actualizacion de reparacion del producto";
            $productoReparacion = ProductoReparacion::find($this->reparacion['id']);
            $producto = $productoReparacion->producto;

            // El cambio de estado se DECIDE aqui y se APLICA al final, junto con
            // su fila de historial: antes el producto pasaba a Inventario aqui
            // arriba y cien lineas mas abajo se escribia un historial que decia
            // 'Reparacion'. La traza mentia, y el auditor encontro cuatro
            // productos asi.
            $terminaYSeMuda = false;
            $sucursalAlmacen = null;

            if ($this->reparacion['estado'] == 'Terminado') {
                $this->reparacion['fecha_recogida'] = $this->reparacion['fecha_entrega'] ?: now()->format('Y-m-d');

                $terminaYSeMuda = $producto->estado === ProductoEstado::Reparacion->value
                    && $producto->ultimaReparacion()?->id === $productoReparacion->id;

                if ($terminaYSeMuda) {
                    $sucursalAlmacen = Sucursal::where('nombre', Sucursal::ALMACEN)->first();
                }

                $descripcion = "Reparacion finalizada";
            }

            // 1. Devolver stock de detalles eliminados
            if (!empty($this->repuestosEliminados)) {
                $repuestosABorrar = ProductoReparacionRepuesto::whereIn('id', $this->repuestosEliminados)
                    ->orderBy('repuesto_id')
                    ->get();

                foreach ($repuestosABorrar as $repuestoReparacion) {
                    // A la sucursal CONGELADA en la linea. Leer
                    // $producto->sucursal_id aqui daria el Almacen, porque unas
                    // lineas mas arriba este mismo metodo reasigna el equipo
                    // alli al terminar la reparacion.
                    $stock->ingresar(
                        $repuestoReparacion->repuesto_id,
                        $repuestoReparacion->sucursal_id,
                        (int) $repuestoReparacion->cantidad,
                    );
                }

                ProductoReparacionRepuesto::destroy($this->repuestosEliminados);
            }

            // 2. Actualizar/Crear detalles y ajustar stock
            $mapRepuestosOriginales = collect($this->repuestosOriginales)->keyBy('id');

            foreach ($this->repuestos as $repuestoRaparacion) {
                $repuesto = Repuesto::find($repuestoRaparacion['repuesto_id']);
                if (!$repuesto)
                    continue;

                if (isset($repuestoRaparacion['id']) && !is_null($repuestoRaparacion['id'])) {
                    // Se relee la fila: de ahi salen la cantidad ANTERIOR y la
                    // sucursal congelada. Antes el "antes" venia de
                    // $this->repuestosOriginales, un array publico de Livewire
                    // sin #[Locked], asi que un payload con 999 ahi devolvia
                    // 998 unidades que nunca existieron.
                    $lineaDB = ProductoReparacionRepuesto::find($repuestoRaparacion['id']);

                    if ($lineaDB) {
                        // ajustarSalida: en una reparacion, subir la cantidad
                        // RETIRA mas stock. La direccion vive en el nombre del
                        // metodo, asi que no se puede copiar invertida del
                        // modulo de compras.
                        $stock->ajustarSalida(
                            $lineaDB->repuesto_id,
                            $lineaDB->sucursal_id,
                            (int) $lineaDB->cantidad,
                            (int) $repuestoRaparacion['cantidad'],
                        );

                        $lineaDB->update([
                            'costo' => $repuestoRaparacion['costo'],
                            'cantidad' => $repuestoRaparacion['cantidad'],
                            'subtotal_costo' => $repuestoRaparacion['subtotal_costo'],
                            'subtotal_costo_bs' => $repuestoRaparacion['subtotal_costo_bs'],
                        ]);
                    }
                } else {
                    // Detalle nuevo: Crear y descontar stock
                    ProductoReparacionRepuesto::create([
                        'producto_reparacion_id' => $this->reparacionModel->id,
                        'repuesto_id' => $repuestoRaparacion['repuesto_id'],
                        // Congelada: de aqui salio la pieza.
                        'sucursal_id' => $this->sucursalRepuestos,
                        'costo' => $repuestoRaparacion['costo'],
                        'cantidad' => $repuestoRaparacion['cantidad'],
                        'subtotal_costo' => $repuestoRaparacion['subtotal_costo'],
                        'subtotal_costo_bs' => $repuestoRaparacion['subtotal_costo_bs'],
                    ]);

                    $stock->retirar(
                        $repuesto->id,
                        $this->sucursalRepuestos,
                        (int) $repuestoRaparacion['cantidad'],
                    );
                }
            }

            $stock->recalcularTotales();

            $productoReparacion->update($this->reparacion);

            if ($terminaYSeMuda) {
                // Estado e historial de una pieza, con la precondicion: si otro
                // ya saco el equipo de reparacion, esto no lo pisa.
                $producto = $estados->cambiar(
                    $producto->id,
                    ProductoEstado::Reparacion,
                    ProductoEstado::Inventario,
                    $descripcion,
                    ['producto_reparacion_id' => $productoReparacion->id],
                );

                // Al terminar, el equipo se muda al Almacen.
                if ($sucursalAlmacen) {
                    $producto->update(['sucursal_id' => $sucursalAlmacen->id]);
                }
            } else {
                // No cambia de estado, pero la edicion deja nota. Con el estado
                // REAL del producto, no con 'Reparacion' a palo seco.
                Bitacora::registrar(
                    $producto,
                    $producto->estado,
                    $descripcion,
                    ['producto_reparacion_id' => $productoReparacion->id],
                );
            }

            $producto->refresh();
            $producto->recalcularCosto();
            $productoReparacion->refresh();

            return $productoReparacion;
        });

        $this->dispatch('refreshTecnicoProductoTable');
        toastr()->success('Reparacion actualizada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
