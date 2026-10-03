<?php

namespace App\Livewire\CompraRepuesto;

use App\Models\CompraRepuesto;
use App\Models\CompraRepuestoDetalle;
use App\Models\Repuesto;
use App\Services\StockRepuestoService;
use App\Traits\RepuestoBuscadorTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class CompraRepuestoEdit extends Component
{
    use RepuestoBuscadorTrait;

    public $compraRepuesto;
    public $compraRepuestoModel;

    public $detalles = [];
    public $detallesOriginales = [];
    public $detallesEliminados = [];

    public $confirmingUpdate = false;

    protected function rules()
    {
        return [
            'detalles' => 'required|array|min:1',
            'detalles.*.costo' => 'required|numeric|min:0',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'compraRepuesto.fecha_compra' => 'required|date',
            'compraRepuesto.costo_total' => 'required|numeric|min:0',
            'compraRepuesto.tipo_cambio' => 'required|numeric|min:1',
            'compraRepuesto.cantidad_repuestos' => 'required|integer|min:1',
            // La unica de las cuatro pantallas que no la validaba, asi que una
            // compra sin sucursal se guardaba limpia y metia un null en
            // StockRepuestoService. Misma regla que su pantalla de crear.
            'compraRepuesto.sucursal_id' => 'required|integer|exists:sucursales,id',
        ];
    }

    protected $messages = [
        'compraRepuesto.sucursal_id.required' => 'Esta compra no tiene sucursal y no se puede guardar.',
        'detalles.required' => 'La compra debe tener al menos un repuesto.',
        'detalles.min' => 'La compra debe tener al menos un repuesto.',
        'detalles.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        'compraRepuesto.fecha_compra.required' => 'La fecha de compra es obligatoria.',
        'compraRepuesto.tipo_cambio.required' => 'El tipo de cambio es obligatorio.',
        'compraRepuesto.tipo_cambio.min' => 'El tipo de cambio debe ser mayor que 0.',
    ];

    public function mount($compraRepuesto)
    {
        $compra = CompraRepuesto::with('detalles')->findOrFail($compraRepuesto->id);
        $this->compraRepuestoModel = $compra;
        $this->compraRepuesto = $compra->toArray();

        foreach ($compra->detalles as $detalle) {
            $this->detalles[] = [
                'id' => $detalle->id,
                'repuesto_id' => $detalle->repuesto->id,
                'nombre' => $detalle->repuesto->nombre,
                'fabricante' => $detalle->repuesto->fabricante,
                // Sin `?? 'Sin modelo'`: ver x-articulo-etiqueta.
                'modelo' => $detalle->repuesto->modelo?->nombre,
                'costo' => $detalle->costo,
                'cantidad' => $detalle->cantidad,
                'subtotal' => $detalle->subtotal,
            ];
        }

        $this->detallesOriginales = $this->detalles;
        $this->recalcularTotales();
    }

    public function render()
    {
        return view('livewire.compra-repuesto.compra-repuesto-edit');
    }

    /**
     * La sucursal a la que entra la compra. La lee RepuestoBuscadorTrait.
     */
    public function sucursalDelDocumento(): ?int
    {
        return isset($this->compraRepuesto['sucursal_id']) && $this->compraRepuesto['sucursal_id'] !== null
            ? (int) $this->compraRepuesto['sucursal_id']
            : null;
    }

    /** El aviso del buscador apagado y el toast del servidor, en un solo sitio. */
    public function motivoSinSucursal(): string
    {
        return 'Esta compra no tiene sucursal: no se le pueden agregar articulos.';
    }

    public function selectRepuesto($id)
    {
        // Faltaba: las dos pantallas de crear si lo comprobaban y esta no, asi
        // que una compra antigua sin sucursal aceptaba lineas y metia un null en
        // StockRepuestoService. Aqui la sucursal es de solo lectura, asi que el
        // mensaje no pide elegirla -- dice que el documento no se puede tocar.
        if (!$this->puedeElegirArticulos()) {
            toastr()->warning($this->motivoSinSucursal());
            return;
        }

        $repuesto = Repuesto::find($id);
        if (!$repuesto)
            return;

        array_unshift($this->detalles, [
            'id' => null,
            'repuesto_id' => $repuesto->id,
            'nombre' => $repuesto->nombre,
            'fabricante' => $repuesto->fabricante,
            'modelo' => $repuesto->modelo?->nombre,
            'costo' => $repuesto->costo,
            'cantidad' => 1,
            'subtotal' => $repuesto->costo,
        ]);

        $this->searchRepuesto = '';
        $this->filteredRepuestos = [];
        $this->recalcularTotales();
    }

    public function updatedDetalles()
    {
        $this->recalcularTotales();
    }

    public function recalcularTotales()
    {
        $costoTotal = 0;
        foreach ($this->detalles as $index => $detalle) {
            $costo = is_numeric($detalle['costo']) ? (float) $detalle['costo'] : 0;
            $cantidad = is_numeric($detalle['cantidad']) ? (int) $detalle['cantidad'] : 0;
            $subtotal = $costo * $cantidad;
            $this->detalles[$index]['subtotal'] = $subtotal;
            $costoTotal += $subtotal;
        }

        $this->compraRepuesto['costo_total'] = round($costoTotal, 2);
        $this->compraRepuesto['cantidad_repuestos'] = count($this->detalles);
    }

    /**
     * Quita un detalle de la lista. Si era un detalle existente, lo marca para eliminación.
     */
    public function eliminarDetalle($index)
    {
        // Si el detalle tiene un 'id', significa que existe en la BD
        if (isset($this->detalles[$index]['id']) && !is_null($this->detalles[$index]['id'])) {
            $this->detallesEliminados[] = $this->detalles[$index]['id'];
        }

        unset($this->detalles[$index]);
        $this->detalles = array_values($this->detalles);
        $this->recalcularTotales();
    }

    /**
     * Muestra el modal de confirmación para actualizar.
     */
    public function confirmUpdate()
    {
        $this->validate();
        $this->confirmingUpdate = true;
    }

    public function update()
    {
        $this->validate();

        $stock = new StockRepuestoService();

        DB::transaction(function () use ($stock) {
            $compra = CompraRepuesto::findOrFail($this->compraRepuesto['id']);
            $user = Auth::user();

            // La sucursal sale de la CABECERA GUARDADA y no del array publico:
            // una compra entra entera a una sucursal y esa no se puede cambiar
            // editando. Las compras viejas la tienen por el backfill.
            $sucursalId = $compra->sucursal_id;

            // 2. Revertir stock de detalles eliminados.
            // Ahora es un RETIRO, y puede fallar: si esas unidades ya se
            // vendieron no hay nada que deshacer y la edicion se bloquea con
            // mensaje. Antes dejaba el contador en negativo sin avisar.
            if (!empty($this->detallesEliminados)) {
                $detallesAborrar = CompraRepuestoDetalle::whereIn('id', $this->detallesEliminados)
                    ->orderBy('repuesto_id')
                    ->get();

                foreach ($detallesAborrar as $detalle) {
                    $stock->retirar($detalle->repuesto_id, $sucursalId, (int) $detalle->cantidad);
                }

                CompraRepuestoDetalle::destroy($this->detallesEliminados);
            }

            // 3. Actualizar o crear detalles y ajustar stock.
            // Ordenado por repuesto_id para que dos guardados simultaneos con
            // las mismas lineas no se bloqueen mutuamente en InnoDB.
            foreach (collect($this->detalles)->sortBy('repuesto_id') as $detalleData) {
                $repuesto = Repuesto::find($detalleData['repuesto_id']);
                if (!$repuesto)
                    continue;

                if (isset($detalleData['id']) && !is_null($detalleData['id'])) {
                    // Se relee la fila para sacar de AHI la cantidad anterior.
                    // Antes salia de $this->detallesOriginales, un array publico
                    // de Livewire sin #[Locked]: un payload con
                    // detallesOriginales[0].cantidad = 999 hacia un increment de
                    // 998 unidades que nunca existieron.
                    $detalleDB = CompraRepuestoDetalle::findOrFail($detalleData['id']);

                    // ajustarEntrada y no una resta a mano: en una compra, subir
                    // la cantidad de una linea INGRESA mas stock. La direccion
                    // vive en el nombre del metodo, que es lo que evita copiar
                    // el signo invertido del modulo de ventas (ya paso).
                    $stock->ajustarEntrada(
                        $detalleDB->repuesto_id,
                        $sucursalId,
                        (int) $detalleDB->cantidad,
                        (int) $detalleData['cantidad'],
                    );

                    $detalleDB->update([
                        'costo' => $detalleData['costo'],
                        'cantidad' => $detalleData['cantidad'],
                        'subtotal' => $detalleData['subtotal'],
                    ]);
                } else {
                    // Es un detalle nuevo, hay que crearlo
                    CompraRepuestoDetalle::create([
                        // Tipo congelado, igual que en las ventas.
                        'tipo' => $repuesto->tipo,
                        'compra_repuesto_id' => $compra->id,
                        'repuesto_id' => $detalleData['repuesto_id'],
                        'costo' => $detalleData['costo'],
                        'cantidad' => $detalleData['cantidad'],
                        'subtotal' => $detalleData['subtotal'],
                    ]);

                    // Una compra SUMA stock. Aqui habia un decrement con el
                    // comentario "Y restar el stock" copiado del modulo de
                    // ventas: anadir una linea a una compra existente restaba
                    // inventario en silencio. Con ingresar() esa confusion ya no
                    // se puede escribir.
                    $stock->ingresar($repuesto->id, $sucursalId, (int) $detalleData['cantidad']);
                }

                // Por query builder: el servicio escribio `cantidad` por fuera,
                // asi que un save() del modelo con el atributo obsoleto en
                // memoria podria pisar el total.
                Repuesto::whereKey($repuesto->id)
                    ->update(['tipo_cambio' => $this->compraRepuesto['tipo_cambio']]);
            }

            // 4. Actualizar la cabecera de la compra.
            // sucursal_id NO viaja aqui a proposito: la sucursal de una compra
            // no se cambia editando, y si no esta en el array que se escribe no
            // hay nada que un payload pueda manipular.
            $compra->update([
                'fecha_compra' => $this->compraRepuesto['fecha_compra'],
                'costo_total' => $this->compraRepuesto['costo_total'],
                'tipo_cambio' => $this->compraRepuesto['tipo_cambio'],
                'cantidad_repuestos' => $this->compraRepuesto['cantidad_repuestos'],
                'user_id' => $user->id
            ]);

            // Una vez al final: el mismo repuesto puede moverse dos veces en un
            // guardado (una linea borrada y otra nueva del mismo articulo).
            $stock->recalcularTotales();
        });

        toastr()->success('Compra de repuesto actualizada exitosamente');
        return redirect()->route('compras.repuestos.editar', $this->compraRepuesto['id']);
    }

}
