<?php

namespace App\Livewire\Compra;

use App\Enums\ArticuloTipo;
use App\Enums\LineaTipo;
use App\Models\Compra;
use App\Models\MetodoPago;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Services\CompraService;
use App\Traits\CarritoBuscadorTrait;
use App\Traits\FilasDePagoFormTrait;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Crear o editar una compra: proveedor, fecha, sucursal y los repuestos y
 * accesorios que trae. Los equipos se dan de alta despues, uno por uno, desde
 * el detalle de la compra (llevan IMEI, fotos y camara: no caben en un carrito).
 *
 * La sucursal se elige al crear y no cambia: es a donde entra el stock.
 *
 * Al crear se registra lo PAGADO AL RECIBIR (filas de pago, Bs o USD). Lo que
 * falte, y lo que sumen los equipos que se carguen despues, queda en cuentas
 * por pagar (PagoProveedorService).
 */
class CompraForm extends Component
{
    use CarritoBuscadorTrait;
    use GuardadoIdempotenteTrait;
    use FilasDePagoFormTrait;

    /** Null al crear. #[Locked]: decide que compra se reescribe. */
    #[Locked]
    public ?int $compraId = null;

    public array $compra = [];

    /** [['tipo', 'id', 'nombre', 'sku', 'cantidad', 'costo'], ...] */
    public array $lineas = [];

    /** Pagos ya registrados (edicion, solo lectura): [['fecha','metodo','monto'], ...]. */
    public array $pagosExistentes = [];

    public function mount(?int $compraId = null): void
    {
        if ($compraId) {
            abort_unless(Auth::user()?->can('compra.edit'), 403);

            $compra = Compra::with(['detalles.repuesto', 'detalles.accesorio', 'pagos.metodo'])->findOrFail($compraId);
            $this->pagosExistentes = $compra->pagos->sortBy('id')->map(fn($p) => [
                'fecha' => $p->fecha->format('d/m/Y H:i'),
                'metodo' => $p->descripcion(),
                'monto' => (float) $p->monto,
            ])->values()->all();
            $this->compraId = $compra->id;
            $this->compra = [
                'proveedor_id' => $compra->proveedor_id,
                'fecha' => $compra->fecha->toDateString(),
                'sucursal_id' => $compra->sucursal_id,
            ];

            foreach ($compra->detalles->whereNull('producto_id') as $d) {
                $articulo = $d->repuesto ?? $d->accesorio;
                $this->lineas[] = [
                    'tipo' => $d->tipo,
                    'id' => (int) $articulo->id,
                    'nombre' => $articulo->nombre,
                    'sku' => $articulo->sku,
                    'cantidad' => (int) $d->cantidad,
                    'costo' => (float) $d->costo,
                ];
            }

            return;
        }

        abort_unless(Auth::user()?->can('compra.create'), 403);

        // Una clave por apertura: reintentar no ingresa el stock dos veces.
        $this->nuevaClaveIdempotencia();
        $this->compra = ['proveedor_id' => null, 'fecha' => now()->toDateString(), 'sucursal_id' => null];
        $this->pagos = [$this->filaPago(MetodoPago::activos()->value('id'))];
    }

    /** Lo que falta pagar de lo cargado aqui (los equipos se suman despues). */
    public function saldoPrevisto(): float
    {
        return round($this->total() - $this->sumaFilas(), 2);
    }

    public function esEdicion(): bool
    {
        return $this->compraId !== null;
    }

    // ------------------------------------------------------------ buscador

    protected function tiposBuscables(): array
    {
        return [LineaTipo::Repuesto, LineaTipo::Accesorio];
    }

    protected function esVenta(): bool
    {
        return false;
    }

    public function sucursalDelDocumento(): ?int
    {
        return !empty($this->compra['sucursal_id']) ? (int) $this->compra['sucursal_id'] : null;
    }

    public function motivoSinSucursal(): string
    {
        return 'Elige primero la sucursal a la que entra la compra.';
    }

    protected function clavesEnCarrito(): array
    {
        return array_map(fn($l) => $l['tipo'] . ':' . $l['id'], $this->lineas);
    }

    protected function agregarLinea(string $tipo, int $id): void
    {
        $articuloTipo = LineaTipo::from($tipo)->articulo();

        if (!$articuloTipo) {
            return;
        }

        $articulo = $articuloTipo->buscar($id);

        if (!$articulo) {
            return;
        }

        array_unshift($this->lineas, [
            'tipo' => $articuloTipo->value,
            'id' => $articulo->id,
            'nombre' => $articulo->nombre,
            'sku' => $articulo->sku,
            'cantidad' => 1,
            'costo' => (float) $articulo->costo,
        ]);
    }

    /** Alta al vuelo desde el modal de articulo (sin stock: entra por esta compra). */
    #[On('articuloCreado')]
    public function articuloCreado(string $tipo, int $id): void
    {
        $this->seleccionarResultado($tipo, $id);
    }

    public function abrirCrearArticulo(string $tipo): void
    {
        $this->dispatch('openArticuloCreateModal', tipo: ArticuloTipo::from($tipo)->value);
    }

    public function quitarLinea(int $index): void
    {
        unset($this->lineas[$index]);
        $this->lineas = array_values($this->lineas);
    }

    /**
     * La sucursal se elige UNA vez: con lineas cargadas, cambiarla haria que el
     * stock se mostrara de un sitio y entrara en otro. En edicion no cambia.
     */
    public function updatedCompraSucursalId(): void
    {
        if ($this->esEdicion()) {
            $this->compra['sucursal_id'] = Compra::whereKey($this->compraId)->value('sucursal_id');

            return;
        }

        $this->busqueda = '';
        $this->resultados = [];
    }

    public function total(): float
    {
        return round(array_sum(array_map(fn($l) => (int) $l['cantidad'] * (float) $l['costo'], $this->lineas)), 2);
    }

    protected function rules(): array
    {
        return [
            'compra.proveedor_id' => 'required|integer|exists:proveedores,id',
            'compra.fecha' => 'required|date',
            'compra.sucursal_id' => $this->esEdicion() ? 'nullable' : 'required|integer|exists:sucursales,id,activa,1',
            'lineas.*.cantidad' => 'required|integer|min:1',
            'lineas.*.costo' => 'required|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'compra.proveedor_id.required' => 'Elige el proveedor.',
            'compra.fecha.required' => 'Indica la fecha de la compra.',
            'compra.sucursal_id.required' => 'Elige la sucursal a la que entra la compra.',
            'compra.sucursal_id.exists' => 'La sucursal no existe o está desactivada.',
            'lineas.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'lineas.*.costo.min' => 'El costo no puede ser negativo.',
        ];
    }

    public function guardar()
    {
        if (!$this->esEdicion()) {
            $this->seguirMonto($this->total());
        }

        $this->validate();

        $articulos = array_map(fn($l) => [
            'tipo' => $l['tipo'], 'id' => $l['id'], 'cantidad' => (int) $l['cantidad'], 'costo' => (float) $l['costo'],
        ], $this->lineas);

        $servicio = app(CompraService::class);

        if ($this->esEdicion()) {
            abort_unless(Auth::user()?->can('compra.edit'), 403);

            DB::transaction(fn() => $servicio->actualizar(
                Compra::lockForUpdate()->findOrFail($this->compraId),
                $this->compra,
                $articulos,
            ));

            toastr()->success('Compra actualizada');

            return redirect()->route('compras.detalle', $this->compraId);
        }

        abort_unless(Auth::user()?->can('compra.create'), 403);

        // El reintento, resuelto antes de abrir la transaccion.
        if ($ya = $this->yaGuardado(Compra::class)) {
            $this->avisarYaGuardado($ya, 'compra');

            return redirect()->route('compras.detalle', $ya->id);
        }

        try {
            $compra = DB::transaction(fn() => $servicio->crear($this->compra, $articulos, Auth::user(), $this->claveIdempotencia, $this->pagos));
        } catch (QueryException $e) {
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(Compra::class)) {
                $this->avisarYaGuardado($ya, 'compra');

                return redirect()->route('compras.detalle', $ya->id);
            }

            throw $e;
        }

        toastr()->success('Compra registrada. Ahora puedes cargar los equipos.');

        return redirect()->route('compras.detalle', $compra->id);
    }

    public function render()
    {
        if (!$this->esEdicion()) {
            $this->seguirMonto($this->total());
        }

        return view('livewire.compra.compra-form', [
            'metodos' => $this->esEdicion() ? collect() : MetodoPago::activos()->get(),
            'proveedores' => Proveedor::orderBy('nombre')->get(['id', 'nombre']),
            'sucursales' => $this->esEdicion()
                ? Sucursal::paraSelect($this->sucursalDelDocumento())
                : Sucursal::activas()->orderBy('nombre')->get(),
        ]);
    }
}
