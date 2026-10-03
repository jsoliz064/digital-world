<?php

namespace App\Livewire\Venta;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Models\VentaProducto;
use App\Services\EstadoProductoService;
use App\Services\RepuestosDeReparacionService;
use App\Traits\ClienteBuscadorTrait;
use App\Traits\GuardadoIdempotenteTrait;
use App\Traits\VentaCarritoTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Registrar una venta de productos, en pagina completa.
 *
 * Antes era un modal, y la tabla del carrito quedaba encerrada en el ancho por
 * defecto de Jetstream: con varios telefonos habia que desplazarse en
 * horizontal para ver lo que se estaba cobrando.
 */
class VentaCreate extends Component
{
    use ClienteBuscadorTrait;
    use GuardadoIdempotenteTrait;
    use VentaCarritoTrait;

    public $searchImei = '';
    public $filteredProductos = [];

    public $detalles = [];
    public $venta = [];
    public $tipo_precio = 'Vendedor';

    public function mount()
    {
        // Una clave por apertura de la pantalla: identifica ESTE intento de
        // venta, asi que un reintento tras cortarse la red trae la misma y no
        // crea una segunda orden.
        $this->nuevaClaveIdempotencia();

        $ultimaCompra = Compra::orderby('id', 'desc')->first();

        $this->venta = [
            'cliente' => null,
            'cliente_id' => null,
            'tipo_cambio' => $ultimaCompra ? $ultimaCompra->tipo_cambio : 7,
            'descuento' => 0,
            'subtotal' => 0,
            'total' => 0,
            'total_bs' => 0,
            'sucursal_id' => null,
            'sucursal_nombre' => null,
        ];
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
        return view('livewire.venta.venta-create', [
            'sucursales' => Sucursal::activas()->orderBy('nombre')->get(),
        ]);
    }

    public function updatedVentaSucursalId()
    {
        // La guarda es por la opcion en blanco del select: antes esto hacia
        // Sucursal::find('')->nombre y reventaba la pantalla entera.
        $sucursal = $this->venta['sucursal_id']
            ? Sucursal::find($this->venta['sucursal_id'])
            : null;

        $this->venta['sucursal_nombre'] = $sucursal?->nombre;
    }

    public function store()
    {
        $this->validate([
            'detalles' => 'required|array|min:1',
            'venta.cliente' => 'nullable|string|min:1|max:255',
            'venta.subtotal' => 'required|numeric|min:0',
            'venta.descuento' => 'required|numeric|min:0',
            'venta.total' => 'required|numeric|min:0',
            'venta.total_bs' => 'required|numeric|min:0',
            'venta.tipo_cambio' => 'required|numeric|min:1',
            'venta.sucursal_id' => 'required|exists:sucursales,id,activa,1',
        ], [
            'detalles.required' => 'Agrega al menos un producto a la venta.',
            'detalles.min' => 'Agrega al menos un producto a la venta.',
            'venta.sucursal_id.required' => 'Selecciona la sucursal de la venta.',
            'venta.sucursal_id.exists' => 'La sucursal elegida no existe o está desactivada.',
            'venta.tipo_cambio.required' => 'Indica el tipo de cambio.',
            'venta.tipo_cambio.min' => 'El tipo de cambio debe ser mayor a cero.',
            'venta.descuento.required' => 'Indica el descuento, o deja 0.',
        ]);

        // El caso normal del reintento: la primera peticion ya cerro. Se
        // responde con la venta que existe en vez de crear una segunda.
        if ($ya = $this->yaGuardado(Venta::class)) {
            $this->avisarYaGuardado($ya, 'venta');

            return redirect()->route('ventas');
        }

        $estados = app(EstadoProductoService::class);

        try {
            $venta = DB::transaction(function () use ($estados) {
                $user = Auth::user();

                $venta = Venta::create([
                    'cliente' => $this->venta['cliente'] ?? null,
                    'cliente_id' => $this->venta['cliente_id'] ?? null,
                    'subtotal' => $this->venta['subtotal'],
                    'descuento' => $this->venta['descuento'],
                    'total' => $this->venta['total'],
                    'tipo_cambio' => $this->venta['tipo_cambio'],
                    'total_bs' => $this->venta['total_bs'],
                    'user_id' => $user->id,
                    'sucursal_id' => $this->venta['sucursal_id'],
                ] + $this->datosDeIdempotencia());

                foreach ($this->detalles as $detalle) {
                    // Bloquea el producto, confirma que sigue vendible, escribe
                    // el estado y su fila de historial. El bloqueo va ANTES de
                    // crear la linea para que el orden de locks sea siempre el
                    // mismo y dos ventas simultaneas no se interbloqueen.
                    $estados->vender(
                        (int) $detalle['producto_id'],
                        "Producto Vendido. Venta: {$venta->id}, Cliente: {$venta->nombreCliente()}, Precio: $ {$detalle['subtotal']}",
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

                // Los repuestos montados en reparaciones que el vendedor decidio
                // cobrar aparte. Va dentro de la transaccion: si falla, la venta
                // entera revierte y no queda media operacion.
                app(RepuestosDeReparacionService::class)
                    ->registrar($venta, $this->repuestosVenta, $user);

                return $venta;
            });
        } catch (QueryException $e) {
            // Dos peticiones a la vez con la misma clave: InnoDB nos hizo
            // esperar en el indice y la otra commiteo. Nuestra transaccion ya
            // revirtio entera, asi que releer es seguro.
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(Venta::class)) {
                $this->avisarYaGuardado($ya, 'venta');

                return redirect()->route('ventas');
            }

            throw $e;
        }

        toastr()->success('Venta registrada exitosamente');

        return redirect()->route('ventas');
    }
}
