<?php

namespace App\Livewire\Repuesto\Modals;

use App\Models\Repuesto;
use App\Models\Sucursal;
use App\Services\StockRepuestoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Mueve unidades de un articulo de una sucursal a otra.
 *
 * Es el unico movimiento de stock del sistema que no nace de una compra, una
 * venta ni una reparacion, y por eso deja su propio documento en
 * repuestos_transferencias: sin el seria invisible en el historial del articulo
 * y el banner de "el balance calculado no coincide" se encenderia por diseño en
 * cada articulo transferido.
 *
 * UN articulo por transferencia, fijado desde la fila que abrio el modal. Es lo
 * que acepta StockRepuestoService::transferir(), y hace que el mensaje de "no
 * hay stock" pueda nombrar el articulo sin ambiguedad.
 *
 * Calcado de ProductoEditSucursalModal en la idea del "conjunto del que se
 * puede sacar" (alli estadosTransferibles(), aqui las sucursales con unidades),
 * y de RepuestoTipoCambioMasivoModal en el resto: carga en openModal() y no en
 * mount(), datos de solo lectura como variables de vista, y el permiso
 * revalidado en el servidor.
 *
 * MAS las tres cosas que a ProductoEditSucursalModal le faltan: exigir origen y
 * destino, comprobar que sean distintos, y dejar traza.
 */
class RepuestoTransferenciaModal extends Component
{
    public $openModal = false;

    /**
     * El articulo a mover. #[Locked] porque es lo que decide de que stock se
     * saca: sin el candado un payload transfiere otro articulo.
     */
    #[Locked]
    public ?int $repuestoId = null;

    /**
     * Los nombres sucursal1_id / sucursal2_id NO son arbitrarios:
     * StockRepuestoService::transferir() lanza su error de origen-igual-a-destino
     * bajo la clave 'sucursal2_id', asi que con cualquier otro nombre ese
     * mensaje no tendria donde pintarse. Son tambien los del modal de productos.
     */
    public $sucursal1_id = '';
    public $sucursal2_id = '';
    public $cantidad = null;

    protected function rules(): array
    {
        return [
            'sucursal1_id' => 'required|integer|exists:sucursales,id',
            // `different` aqui y no solo la comprobacion del servicio: asi el
            // error llega antes de abrir la transaccion, y con el mensaje
            // pegado al campo.
            'sucursal2_id' => 'required|integer|exists:sucursales,id,activa,1|different:sucursal1_id',
            'cantidad' => 'required|integer|min:1',
        ];
    }

    protected $messages = [
        'sucursal1_id.required' => 'Elige la sucursal de origen.',
        'sucursal2_id.required' => 'Elige la sucursal de destino.',
        'sucursal2_id.different' => 'El origen y el destino tienen que ser sucursales distintas.',
        'sucursal2_id.exists' => 'La sucursal de destino no existe o está desactivada.',
        'cantidad.required' => 'Indica cuantas unidades transferir.',
        'cantidad.min' => 'La cantidad debe ser al menos 1.',
    ];

    #[On('openRepuestoTransferenciaModal')]
    public function openModal($repuestoId): void
    {
        // Reset dirigido y no $this->reset() a secas: el articulo se asigna
        // justo despues y openModal tiene que quedar en true.
        $this->reset(['sucursal1_id', 'sucursal2_id', 'cantidad']);
        $this->resetValidation();

        $this->repuestoId = (int) $repuestoId;
        $this->openModal = true;
    }

    public function store(): void
    {
        // El @can del blade solo oculta el boton; el evento Livewire es
        // invocable desde el cliente, asi que el permiso se revalida aqui.
        abort_unless(Auth::user()->can('repuesto.transferir'), 403);

        $this->validate();

        $stock = new StockRepuestoService();
        $cantidad = (int) $this->cantidad;

        // Aviso amable ANTES de intentarlo, con el numero a la vista. NO es la
        // guarda: la de verdad es el `WHERE cantidad >= ?` de retirar(), porque
        // entre este SELECT y ese UPDATE cabe otra venta.
        $hay = $stock->disponible($this->repuestoId, (int) $this->sucursal1_id);

        if ($hay < $cantidad) {
            $this->addError('cantidad', "Solo hay {$hay} unidad(es) en la sucursal de origen.");
            return;
        }

        try {
            DB::transaction(function () use ($stock, $cantidad) {
                $stock->transferir(
                    $this->repuestoId,
                    (int) $this->sucursal1_id,
                    (int) $this->sucursal2_id,
                    $cantidad,
                    Auth::id(),
                );

                // transferir() NO lo llama: sin esto el total cacheado se queda
                // atras hasta la siguiente operacion del articulo.
                $stock->recalcularTotales();
            });
        } catch (ValidationException $e) {
            // Los errores de stock del servicio son del usuario y tienen que
            // llegar al formulario, no al toastr generico de abajo. Llegan bajo
            // la clave 'detalles' (retirar()) y 'sucursal2_id' (transferir()).
            throw $e;
        } catch (\Throwable $th) {
            toastr()->error('Error al transferir el stock');
            return;
        }

        $this->dispatch('refreshRepuestoTable');
        toastr()->success('Stock transferido exitosamente');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        // Variables de vista y no propiedades publicas: no viajan en el payload
        // de Livewire y reset() no puede vaciarlas. Con el modal cerrado, 0
        // consultas.
        $repuesto = null;
        $reparto = collect();

        if ($this->openModal && $this->repuestoId) {
            $repuesto = Repuesto::with('stocks.sucursal')->find($this->repuestoId);
            $reparto = $repuesto ? $repuesto->stocks->sortByDesc('cantidad')->values() : collect();
        }

        return view('livewire.repuesto.modals.repuesto-transferencia-modal', [
            'repuesto' => $repuesto,
            'reparto' => $reparto,
            // El conjunto del que se puede sacar. Una sucursal sin unidades no
            // es un origen posible, y ofrecerla solo invita al error.
            'origenes' => $reparto->filter(fn($s) => (int) $s->cantidad > 0)->values(),
            // Solo el destino se limita a las activas: el origen sale del reparto,
            // y vaciar una sucursal desactivada tiene que seguir siendo posible.
            'sucursales' => $this->openModal ? Sucursal::activas()->orderBy('nombre')->get() : collect(),
        ]);
    }
}
