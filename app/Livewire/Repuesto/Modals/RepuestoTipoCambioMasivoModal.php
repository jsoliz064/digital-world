<?php

namespace App\Livewire\Repuesto\Modals;

use App\Enums\BitacoraEvento;
use App\Models\Bitacora;
use App\Models\Repuesto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Actualiza el tipo de cambio de TODOS los repuestos de una sola vez.
 *
 * Es seguro a nivel de datos: `repuestos` no tiene columnas en bolivianos
 * derivadas del tipo de cambio (costo y precio estan en USD), y las ventas y
 * compras ya registradas guardan su propia copia del tipo de cambio, asi que
 * ningun documento historico se recalcula.
 */
class RepuestoTipoCambioMasivoModal extends Component
{
    public $openModal = false;

    /** Vacio a proposito: el usuario debe teclear el valor siempre. */
    public $tipoCambio = null;

    /** Segundo paso: la escritura toca todo el catalogo y no tiene deshacer. */
    public $confirmando = false;

    public $totalRepuestos = 0;

    protected $rules = [
        // Misma regla que ya rige el campo en RepuestoCreateModal/RepuestoEditModal.
        'tipoCambio' => 'required|numeric|min:1',
    ];

    protected $messages = [
        'tipoCambio.required' => 'Debe ingresar el tipo de cambio.',
        'tipoCambio.numeric' => 'El tipo de cambio debe ser un numero.',
        'tipoCambio.min' => 'El tipo de cambio debe ser mayor o igual a 1.',
    ];

    /**
     * Todo se carga aqui y nada en mount(): reset() restaura las propiedades a
     * sus valores *declarados*, no al estado posterior a mount(), asi que
     * cargar ahi deja el modal vacio en la segunda apertura.
     */
    #[On('openRepuestoTipoCambioMasivoModal')]
    public function openModal()
    {
        $this->totalRepuestos = Repuesto::count();
        $this->openModal = true;
    }

    public function confirmar()
    {
        $this->validate();
        $this->confirmando = true;
    }

    public function volver()
    {
        $this->confirmando = false;
    }

    public function update()
    {
        // El @can del blade solo oculta el boton; el evento Livewire es
        // invocable desde el cliente, asi que el permiso se revalida aqui.
        abort_unless(Auth::user()->can('repuesto.tipo-cambio-masivo'), 403);

        $this->validate();

        try {
            // Un unico UPDATE, no el bucle find()+update() de
            // ProductoEstadoMasivoModal. Pero un update() del query builder NO
            // pasa por el observer, y esto cambia la tasa de TODO el catalogo:
            // sin las filas de abajo, la bitacora de cada repuesto no contaria
            // que su tipo de cambio se movio, ni quien lo movio.
            $afectados = DB::transaction(function () {
                $tasa = (float) $this->tipoCambio;

                // Solo los que de verdad cambian: los que ya estaban en esa tasa
                // no tienen nada que contar.
                $anteriores = Repuesto::query()
                    ->whereRaw('ABS(tipo_cambio - ?) >= 0.000001', [$tasa])
                    ->pluck('tipo_cambio', 'id');

                $afectados = Repuesto::query()->update(['tipo_cambio' => $this->tipoCambio]);

                // Una sola insercion para todo el catalogo. Directa y no por
                // registrar(): el modelo no aplica casts en un insert masivo, asi
                // que `cambios` va ya en JSON y la fecha y el autor a mano.
                $ahora = now();
                $filas = $anteriores->map(fn($antes, $id) => [
                    'auditable_type' => (new Repuesto)->getMorphClass(),
                    'auditable_id' => $id,
                    'evento' => BitacoraEvento::Editado->value,
                    'descripcion' => 'Tipo de cambio masivo de todo el catálogo',
                    'cambios' => json_encode(['tipo_cambio' => [$antes, $tasa]]),
                    'user_id' => Auth::id(),
                    'created_at' => $ahora,
                ])->values()->all();

                foreach (array_chunk($filas, 500) as $lote) {
                    Bitacora::insert($lote);
                }

                return $afectados;
            });

            $this->dispatch('refreshRepuestoTable');
            toastr()->success("Tipo de cambio actualizado en {$afectados} repuesto(s)");
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error('Error al actualizar el tipo de cambio');
        }
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.repuesto.modals.repuesto-tipo-cambio-masivo-modal', [
            // Variable de vista, no propiedad publica: no viaja en el payload de
            // Livewire y reset() no puede vaciarla. El guard evita consultar
            // con el modal cerrado.
            'resumenActual' => $this->openModal
                ? Repuesto::select('tipo_cambio')
                    ->selectRaw('count(*) as total')
                    ->groupBy('tipo_cambio')
                    ->orderByDesc('total')
                    ->get()
                : collect(),
        ]);
    }
}
