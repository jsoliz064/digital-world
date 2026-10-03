<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ReparacionTipo;
use App\Models\Producto;
use App\Models\Bitacora;
use App\Models\ProductoModelo;
use App\Models\ProductoReparacion;
use App\Models\Tecnicos;
use App\Models\VentaProducto;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use App\Models\Repuesto;
use App\Models\ProductoReparacionRepuesto;
use App\Models\RepuestoCategoria;

/**
 * Reparacion sobre un producto YA VENDIDO. Cubre los dos casos que existen:
 *
 *  - Garantia:        dentro del plazo, la asumimos nosotros.
 *  - Trabajo Externo: la garantia vencio y lo paga el cliente.
 *
 * Es un solo componente y no dos porque la parte delicada -- devolver, ajustar
 * y descontar el stock de los repuestos -- es identica, y duplicarla
 * significaria que un arreglo futuro en una copia no llegaria a la otra.
 */
class ProductoReparacionClienteModal extends Component
{
    public $openModal = false;
    public $producto;
    public $detalle;

    /** Modo activo: Garantia o Externo. */
    public $tipo = 'Garantia';

    public $reparacion = [];

    public $repuestos = [];
    /**
     * La sucursal de donde salen las piezas de esta reparacion.
     *
     * La elige el tecnico y se CONGELA en cada linea
     * (productos_reparaciones_repuestos.sucursal_id). No se deduce de
     * $producto->sucursal_id: al terminar una reparacion el equipo se reasigna
     * al Almacen en otra request, asi que la sucursal actual del producto no
     * dice de donde salio la pieza. Si el stock volviera ahi, la sucursal real
     * quedaria en negativo para siempre.
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

    public $tecnico;
    public $tecnicos;

    public function render()
    {
        // En render() y no en mount(): es variable de vista, asi que no viaja en
        // el payload de Livewire y reset() no la vacia.
        return view('livewire.producto.modals.producto-reparacion-cliente-modal', [
            'sucursales' => \App\Models\Sucursal::activas()->orderBy('nombre')->get(),
        ]);
    }

    #[On('openProductoGarantiaModal')]
    public function openModalGarantia($id)
    {
        $this->abrir($id, ReparacionTipo::Garantia->value);
    }

    #[On('openProductoTrabajoExternoModal')]
    public function openModalTrabajoExterno($id)
    {
        $this->abrir($id, ReparacionTipo::Externo->value);
    }

    public function esExterno(): bool
    {
        return $this->tipo === ReparacionTipo::Externo->value;
    }

    /** Etiqueta para titulos y mensajes. */
    public function etiquetaTipo(): string
    {
        return ReparacionTipo::from($this->tipo)->label();
    }

    private function abrir($id, string $tipo)
    {
        $this->tipo = $tipo;

        $producto = Producto::find($id);
        $this->tecnicos = Tecnicos::all();
        $this->categorias = RepuestoCategoria::all();
        $this->modelos = ProductoModelo::all();
        $this->producto = $producto;
        $this->openModal = true;

        // La linea de venta se carga en los dos modos: en garantia da el
        // venta_id, y en trabajo externo sirve para mostrar cuando vencio.
        // El ?-> cubre un producto marcado Vendido sin linea de venta.
        $ventaProducto = $producto->ventaProducto;
        $this->detalle = $ventaProducto
            ? VentaProducto::where('producto_id', $producto->id)
                ->where('venta_id', $ventaProducto->venta_id)->first()
            : null;

        // Cada modo abre SU reparacion pendiente: un producto puede tener a la
        // vez una garantia y un trabajo externo abiertos.
        $productoReparacion = ProductoReparacion::where('producto_id', $producto->id)
            ->where('tipo', $this->tipo)
            ->where('estado', 'Pendiente')
            ->first();

        $this->tecnico = $productoReparacion ? $productoReparacion->tecnico : null;

        $this->reparacion = $productoReparacion ? $productoReparacion->toArray() : $this->initialReparacion();
        $this->reparacion['pagado'] = (bool)$this->reparacion['pagado'];
        $this->reparacion['garantia_tecnico'] = (bool)$this->reparacion['garantia_tecnico'];

        $this->repuestos = [];
        $this->repuestosOriginales = [];
        $this->repuestosEliminados = [];
        $this->searchRepuesto = '';
        $this->filteredRepuestos = [];

        if ($productoReparacion) {
            foreach ($productoReparacion->repuestos as $reparacionRepuesto) {
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
        }
    }

    public function initialReparacion()
    {
        return [
            'costo' => 0,
            'costo_repuestos' => 0,
            'tipo_cambio' => $this->producto->compra->tipo_cambio ?? 1,
            'costo_total' => 0,
            'costo_total_bs' => 0,
            'cobro_cliente' => 0,
            'fecha_entrega' => '',
            'pagado' => false,
            'garantia_tecnico' => false
        ];
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
            'subtotal_costo_bs' => $repuesto->costo * ($this->reparacion['tipo_cambio'] ?? 1),
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
            $subtotalCostoRepuestoBs = $subtotalCostoRepuesto * ($this->reparacion['tipo_cambio'] ?? 1);
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
        $this->validate([
            'reparacion.costo' => 'required|numeric|min:0',
            'reparacion.costo_repuestos' => 'required|numeric|min:0',
            'reparacion.tipo_cambio' => 'required|numeric|min:0',
            'reparacion.costo_total' => 'required|numeric|min:0',
            'reparacion.costo_total_bs' => 'required|numeric|min:0',
            'reparacion.tecnico_id' => 'required',
            'reparacion.fecha_entrega' => 'required',
            'reparacion.garantia_tecnico' => 'required',
            'reparacion.pagado' => 'required',
            // Solo el trabajo externo se le cobra al cliente.
            'reparacion.cobro_cliente' => $this->esExterno() ? 'required|numeric|min:0' : 'nullable',
        ], [
            'reparacion.cobro_cliente.required' => 'Indique cuanto se le cobra al cliente.',
            'reparacion.cobro_cliente.min' => 'El cobro no puede ser negativo.',
        ]);

        $stock = new \App\Services\StockRepuestoService();

        DB::transaction(function () use ($stock) {
            $dataToSave = [
                'tecnico_id' => $this->reparacion['tecnico_id'],
                'costo' => $this->reparacion['costo'],
                'costo_repuestos' => $this->reparacion['costo_repuestos'],
                'tipo_cambio' => $this->reparacion['tipo_cambio'],
                'costo_total' => $this->reparacion['costo_total'],
                'costo_total_bs' => $this->reparacion['costo_total_bs'],
                'repuestos_tecnico' => isset($this->reparacion['repuestos_tecnico']) ? $this->reparacion['repuestos_tecnico'] : null,
                'repuestos_propios' => isset($this->reparacion['repuestos_propios']) ? $this->reparacion['repuestos_propios'] : null,
                'repuestos_devolver' => isset($this->reparacion['repuestos_devolver']) ? $this->reparacion['repuestos_devolver'] : null,
                'fecha_entrega' => $this->reparacion['fecha_entrega'],
                'garantia_tecnico' => $this->reparacion['garantia_tecnico'],
                'pagado' => $this->reparacion['pagado'],
                'cobro_cliente' => $this->esExterno() ? ($this->reparacion['cobro_cliente'] ?? 0) : 0,
            ];

            if (isset($this->reparacion['id'])) {
                $productoReparacion = ProductoReparacion::find($this->reparacion['id']);
                $productoReparacion->update($dataToSave);
            } else {
                $tecnico = Tecnicos::find($this->reparacion['tecnico_id']);
                $dataToSave['producto_id'] = $this->producto->id;
                $dataToSave['tipo'] = $this->tipo;
                // Solo la garantia cuelga de una venta. Un trabajo externo es
                // independiente: la venta que lo origino ya se cerro.
                $dataToSave['venta_id'] = $this->esExterno() ? null : $this->detalle?->venta_id;
                $productoReparacion = ProductoReparacion::create($dataToSave);

                $motivo = $this->esExterno() ? 'por trabajo externo' : 'por garantia';
                Bitacora::registrar(
                    $this->producto,
                    $this->eventoBitacora(),
                    "Producto en reparacion {$motivo} con el tecnico {$tecnico->nombre}",
                    ['producto_reparacion_id' => $productoReparacion->id],
                );
            }

            // 1. Devolver stock de detalles eliminados
            if (!empty($this->repuestosEliminados)) {
                $repuestosABorrar = ProductoReparacionRepuesto::whereIn('id', $this->repuestosEliminados)
                    ->orderBy('repuesto_id')
                    ->get();

                foreach ($repuestosABorrar as $repuestoReparacion) {
                    // A la sucursal CONGELADA en la linea, no a la actual del
                    // producto: el equipo pudo mudarse al Almacen al terminar
                    // una reparacion anterior.
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
                    // Se relee la fila: de ahi sale la cantidad ANTERIOR y la
                    // sucursal congelada. Antes el "antes" venia de
                    // $this->repuestosOriginales, un array publico de Livewire
                    // sin #[Locked], asi que un payload con 999 ahi devolvia
                    // 998 unidades que nunca existieron.
                    $lineaDB = ProductoReparacionRepuesto::find($repuestoRaparacion['id']);

                    if ($lineaDB) {
                        // ajustarSalida: en una reparacion, subir la cantidad
                        // RETIRA mas stock. La direccion vive en el nombre.
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
                        'producto_reparacion_id' => $productoReparacion->id,
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

            // Ya no se escribe `garantia_activa`: el distintivo de la tabla
            // se deduce de si existe una reparacion pendiente de ese tipo, que
            // es el dato real y no puede quedarse desincronizado.

            // El trabajo externo lo paga el cliente: no es costo de inventario
            // y no debe tocar el costo de un telefono ya vendido.
            if (!$this->esExterno()) {
                $this->producto->refresh();
                $this->producto->recalcularCosto();
            }
        });

        $this->dispatch('refreshProductoTable');
        toastr()->success($this->etiquetaTipo() . ' actualizada exitosamente');
        $this->reset();
    }

    public function finalizarReparacion()
    {
        DB::transaction(function () {
            $productoReparacion = ProductoReparacion::find($this->reparacion['id']);
            $productoReparacion->update([
                'estado' => 'Terminado',
                'fecha_recogida' => now()->format('Y-m-d'),
            ]);

            $motivo = $this->esExterno() ? 'de trabajo externo' : 'de garantia';
            Bitacora::registrar(
                $this->producto,
                $this->eventoBitacora(),
                "Reparacion {$motivo} finalizada con el tecnico {$productoReparacion->tecnico?->nombre}",
                ['producto_reparacion_id' => $productoReparacion->id],
            );
        });

        $this->dispatch('refreshProductoTable');
        toastr()->success('Reparacion finalizada exitosamente');
        $this->reset();
    }

    /**
     * El evento de estas filas NO es 'Reparacion', y a proposito.
     *
     * Antes se escribia 'Reparacion' literal mientras el telefono seguia en
     * Vendido -- una garantia de un equipo ya vendido no lo devuelve al taller
     * del inventario --, y el auditor lo leia como "historial distinto del estado
     * real". Un evento que no es un estado no puede contradecir al estado.
     */
    protected function eventoBitacora(): string
    {
        return $this->esExterno() ? 'externo' : 'garantia';
    }

    public function closeModal()
    {
        $this->reset();
    }
}
