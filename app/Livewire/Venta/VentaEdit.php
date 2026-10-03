<?php

namespace App\Livewire\Venta;

use App\Models\Cliente;
use App\Models\Venta;
use App\Models\VentaProducto;
use App\Services\EstadoProductoService;
use App\Services\RepuestosDeReparacionService;
use App\Traits\ClienteBuscadorTrait;
use App\Traits\VentaCarritoTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Agregar productos a una venta ya registrada, en pagina completa.
 *
 * No es un editor libre de la venta: las lineas ya registradas se muestran pero
 * no se tocan, igual que en el modal que esta pantalla reemplaza. Quitar una de
 * ellas tiene que devolver el telefono al inventario y escribir su historial, y
 * de eso se encarga VentaDetalleDestroyModal desde la pantalla de detalle.
 */
class VentaEdit extends Component
{
    use ClienteBuscadorTrait;
    use VentaCarritoTrait;

    public $ventaId;
    public $sucursalNombre;
    public $vendedorNombre;

    public $searchImei = '';
    public $filteredProductos = [];

    public $detalles = [];
    public $venta = [];
    public $tipo_precio = 'Vendedor';

    public function mount($venta)
    {
        $modelo = Venta::with(['detalles.producto', 'sucursal', 'user'])->findOrFail($venta->id);

        $this->ventaId = $modelo->id;
        $this->sucursalNombre = $modelo->sucursal?->nombre;
        $this->vendedorNombre = $modelo->user?->name;

        // Solo las columnas de la cabecera. Un toArray() del modelo arrastraria
        // tambien las relaciones cargadas y Livewire las serializaria enteras
        // en cada peticion.
        $this->venta = [
            'cliente' => $modelo->cliente,
            'subtotal' => $modelo->subtotal,
            'descuento' => $modelo->descuento,
            'total' => $modelo->total,
            'tipo_cambio' => $modelo->tipo_cambio,
            'total_bs' => $modelo->total_bs,
            'sucursal_id' => $modelo->sucursal_id,
        ];

        foreach ($modelo->detalles as $detalle) {
            $this->detalles[] = [
                'producto_id' => $detalle->producto_id,
                'descripcion' => $detalle->producto?->descripcion,
                'imei' => $detalle->producto?->imei,
                'costo' => $detalle->costo,
                'precio' => $detalle->precio,
                'descuento' => $detalle->descuento,
                'subtotal' => $detalle->subtotal,
                'is_existing' => true,
            ];
        }

        $this->recalcularSubtotales();
    }


    /**
     * Guarda la ficha elegida en la cabecera.
     *
     * Escribe las DOS claves: `cliente_id` es el enlace, y `cliente` es el nombre
     * que queda CONGELADO en el documento -- el archivo de a quien se le vendio,
     * que no se reescribe si manana le corrigen el nombre a la ficha.
     */
    protected function fijarCliente(?Cliente $cliente): void
    {
        $this->venta['cliente_id'] = $cliente?->id;
        $this->venta['cliente'] = $cliente?->nombre;
    }

    public function clienteIdElegido(): ?int
    {
        return isset($this->venta['cliente_id']) ? (int) $this->venta['cliente_id'] : null;
    }

    public function render()
    {
        return view('livewire.venta.venta-edit');
    }

    /** La venta ya tiene sucursal: solo se ofrecen equipos de esa. */
    protected function sucursalIdParaBusqueda(): ?int
    {
        return $this->venta['sucursal_id'] ?? null;
    }

    /** Lo ya registrado queda congelado. */
    protected function lineaEditable(array $detalle): bool
    {
        return !($detalle['is_existing'] ?? false);
    }

    /** La tasa de una venta registrada no se toca al agregar productos. */
    protected function debeSembrarTipoCambio(): bool
    {
        return false;
    }

    public function save()
    {
        $this->validate([
            'detalles' => 'required|array|min:1',
            'venta.cliente' => 'nullable|string|min:1|max:255',
            'venta.descuento' => 'required|numeric|min:0',
            'venta.tipo_cambio' => 'required|numeric|min:1',
        ], [
            'detalles.required' => 'La venta debe tener al menos un producto.',
            'detalles.min' => 'La venta debe tener al menos un producto.',
        ]);

        // El reintento, con la clave natural de la operacion.
        //
        // Esta pantalla AGREGA lineas a una venta que ya existe, asi que no
        // puede usar GuardadoIdempotenteTrait: la clave de ventas se escribio
        // al crearla y este guardado no crea cabecera. Lo que identifica al
        // intento es el par (venta, producto), que ya es unico en la base. Si
        // todas las lineas nuevas estan puestas, el guardado anterior si entro.
        $nuevos = collect($this->detalles)
            ->reject(fn($detalle) => $detalle['is_existing'])
            ->pluck('producto_id');

        $yaPuestas = $nuevos->isNotEmpty() && VentaProducto::where('venta_id', $this->ventaId)
            ->whereIn('producto_id', $nuevos)
            ->count() === $nuevos->count();

        if ($yaPuestas) {
            toastr()->info('Estos productos ya se habian agregado a la venta. No se duplicaron.');

            return redirect()->route('ventas.detalles', $this->ventaId);
        }

        $estados = app(EstadoProductoService::class);

        $venta = DB::transaction(function () use ($estados) {
            $user = Auth::user();
            $venta = Venta::findOrFail($this->ventaId);

            $venta->update([
                'cliente' => $this->venta['cliente'] ?? null,
                'cliente_id' => $this->venta['cliente_id'] ?? null,
                'subtotal' => $this->venta['subtotal'],
                'descuento' => $this->venta['descuento'],
                'total' => $this->venta['total'],
                'tipo_cambio' => $this->venta['tipo_cambio'],
                'total_bs' => $this->venta['total_bs'],
            ]);

            foreach ($this->detalles as $detalle) {
                if ($detalle['is_existing']) {
                    continue;
                }

                // Bloquea, confirma que sigue vendible, escribe estado e
                // historial. Antes del create de la linea, para que el orden de
                // locks sea el mismo que en VentaCreate.
                $estados->vender(
                    (int) $detalle['producto_id'],
                    "Producto Vendido en venta #{$venta->id}",
                    ['venta_id' => $venta->id],
                );

                VentaProducto::create([
                    'producto_id' => $detalle['producto_id'],
                    'costo' => $detalle['costo'],
                    'precio' => $detalle['precio'],
                    'descuento' => $detalle['descuento'],
                    'subtotal' => $detalle['subtotal'],
                    'tipo_cambio' => $this->venta['tipo_cambio'],
                    'subtotal_bs' => $detalle['subtotal'] * $this->venta['tipo_cambio'],
                    'garantia_meses' => $detalle['garantia_meses'],
                    'garantia_fecha_exp' => $detalle['garantia_fecha_exp'],
                    'venta_id' => $venta->id,
                    'sucursal_id' => $venta->sucursal_id
                ]);
            }

            // Los repuestos de reparacion que se cobran aparte. Cada llamada
            // crea su propia cabecera de venta de repuestos enlazada a esta
            // venta; la restriccion unica impide cobrar dos veces la misma
            // pieza aunque se agreguen productos en varias tandas.
            app(RepuestosDeReparacionService::class)
                ->registrar($venta, $this->repuestosVenta, $user);

            return $venta;
        });

        toastr()->success('Venta actualizada exitosamente');

        return redirect()->route('ventas.detalles', $this->ventaId);
    }
}
