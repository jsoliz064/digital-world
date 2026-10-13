<?php

namespace App\Livewire\Producto\Modals;

use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\CompraService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * La compra de un equipo (a quien se le compro) y, con compra.edit, pasarlo a
 * otra compra cuando se cargo en la equivocada: CompraService::moverProducto()
 * mueve su linea y recalcula las dos compras, sin borrar ni recrear el equipo.
 *
 * Lo abren Productos ("Ver compra") y el detalle de una compra ("Cambiar de
 * compra", que entra directo a elegir).
 */
class ProductoCompraModal extends Component
{
    private const ULTIMAS = 10;

    public bool $openModal = false;

    public ?int $productoId = null;

    /** Mostrar la lista para elegir la compra nueva. */
    public bool $cambiando = false;

    public $proveedorId = '';

    public $buscarNumero = '';

    public ?int $destinoId = null;

    #[On('openProductoCompraModal')]
    public function open(int $id, bool $cambiar = false): void
    {
        abort_unless(auth()->user()?->canAny(['compra.detalle', 'compra.edit']), 403);

        $this->reset('proveedorId', 'buscarNumero', 'destinoId');
        $this->resetErrorBag();
        $this->productoId = $id;
        $this->cambiando = $cambiar && auth()->user()->can('compra.edit');
        $this->openModal = true;
    }

    public function closeModal(): void
    {
        $this->reset('openModal', 'productoId', 'cambiando', 'proveedorId', 'buscarNumero', 'destinoId');
    }

    public function empezarCambio(): void
    {
        abort_unless(auth()->user()->can('compra.edit'), 403);
        $this->cambiando = true;
    }

    public function updatedProveedorId(): void
    {
        $this->destinoId = null;
    }

    public function updatedBuscarNumero(): void
    {
        $this->destinoId = null;
    }

    public function elegir(int $compraId): void
    {
        $this->resetErrorBag();
        $this->destinoId = $compraId;
    }

    public function mover(CompraService $compras): void
    {
        abort_unless(auth()->user()->can('compra.edit'), 403);

        $producto = Producto::findOrFail($this->productoId);
        $origenId = $producto->compraDetalle?->compra_id;

        if (!$this->destinoId || !$origenId) {
            return;
        }

        try {
            $origen = DB::transaction(function () use ($compras, $producto, $origenId) {
                // Orden de bloqueo de la casa: las compras por id, despues el equipo (el servicio).
                $bloqueadas = Compra::whereIn('id', [$origenId, $this->destinoId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $destino = $bloqueadas->get($this->destinoId) ?? throw ValidationException::withMessages(['compra' => 'Esa compra ya no existe.']);

                return $compras->moverProducto($producto, $destino);
            });
        } catch (ValidationException $e) {
            $this->addError('compra', collect($e->errors())->flatten()->first());

            return;
        }

        $destino = Compra::with('proveedor')->find($this->destinoId);
        toastr()->success("El equipo pasó de la compra #{$origen->id} a la #{$destino->id} ({$destino->proveedor?->nombre}).");

        $this->dispatch('refreshProductoTable');
        $this->dispatch('refreshCompraDetalle');
        $this->dispatch('pagosProveedorActualizados');
        $this->closeModal();
    }

    public function render(CompraService $compras)
    {
        $datos = ['producto' => null, 'compra' => null, 'motivo' => null, 'candidatas' => collect(), 'destino' => null, 'proveedores' => collect()];

        if ($this->openModal) {
            $producto = Producto::with(['modelo', 'compraDetalle.sucursal', 'compra.proveedor', 'permuta'])->find($this->productoId);
            $datos['producto'] = $producto;
            $datos['compra'] = $producto?->compra;
            $datos['motivo'] = $producto ? $compras->motivoNoMovible($producto) : null;

            if ($producto && $this->cambiando && !$datos['motivo'] && $datos['compra']) {
                $datos['proveedores'] = Proveedor::orderBy('nombre')->get(['id', 'nombre']);
                $datos['candidatas'] = Compra::query()
                    ->with('proveedor')
                    ->conConteoReclamos()
                    ->withCount(['detalles as equipos' => fn($q) => $q->whereNotNull('producto_id')])
                    ->where('compras.id', '!=', $datos['compra']->id)
                    ->when($this->proveedorId, fn($q) => $q->where('proveedor_id', $this->proveedorId))
                    ->when(trim((string) $this->buscarNumero) !== '', fn($q) => $q->where('compras.id', (int) ltrim(trim($this->buscarNumero), '#')))
                    ->orderByDesc('fecha')->orderByDesc('compras.id')
                    ->limit(self::ULTIMAS)
                    ->get();

                if ($this->destinoId) {
                    $datos['destino'] = Compra::with('proveedor')->find($this->destinoId);
                    // El aviso de lo que pasara (o por que no se puede), antes de confirmar.
                    $datos['motivo_destino'] = $datos['destino'] ? $compras->motivoNoMovible($producto, $datos['destino']) : null;
                    $datos['origen_queda_vacia'] = !$datos['compra']->esBorrador() && $datos['compra']->detalles()->count() === 1;
                }
            }
        }

        return view('livewire.producto.modals.producto-compra-modal', $datos);
    }
}
