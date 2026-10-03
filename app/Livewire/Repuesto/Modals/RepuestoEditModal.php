<?php

namespace App\Livewire\Repuesto\Modals;

use App\Enums\RepuestoTipo;
use App\Models\Repuesto;
use App\Traits\RepuestoAccesorioTrait;
use App\Traits\RepuestoStockSucursalTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\On;

class RepuestoEditModal extends Component
{
    use RepuestoAccesorioTrait;
    use RepuestoStockSucursalTrait;

    public $openModal = false;
    public $repuesto = [];
    protected function rules()
    {
        $reglas = [
        'repuesto.tipo' => ['required', 'in:' . implode(',', RepuestoTipo::values())],
        'repuesto.nombre' => 'required|string|max:255',
        'repuesto.fabricante' => 'nullable|string|max:255',
        'repuesto.costo' => 'required|numeric|min:0',
        'repuesto.tipo_cambio' => 'required|numeric|min:1',
        'repuesto.precio' => 'required|numeric|min:0',
        'repuesto.producto_modelo_id' => [
            'nullable',
            'integer',
            'required_if:repuesto.tipo,' . RepuestoTipo::Repuesto->value,
        ],
        'repuesto.repuesto_categoria_id' => 'nullable|integer',
        // Sintaxis de array obligatoria: una regla regex: no puede ir en un
        // string delimitado por | porque Laravel partiria la propia regex.
        'repuesto.color' => ['nullable', 'string', 'max:50'],
        'repuesto.color_hex' => ['nullable', 'string', 'regex:/^#[A-Fa-f0-9]{6}$/'],
        ];

        // Un accesorio no ensena esos campos, asi que tampoco los valida.
        $reglas = array_merge($reglas, $this->reglasDeStock());

        return $this->esAccesorio() ? $this->sinReglasDeRepuesto($reglas) : $reglas;
    }

    protected $messages = [
        'repuesto.color_hex.regex' => 'El color debe ser un hexadecimal valido (ej: #1F2937).',
        'repuesto.nombre' => 'Debe ingresar un nombre',
        'repuesto.fabricante' => 'Debe ingresar un fabricante',
        'repuesto.costo' => 'Debe ingresar un costo',
        'repuesto.tipo_cambio' => 'Debe ingresar el tipo de cambio',
        'repuesto.precio' => 'Debe ingresar un precio',
        'stockSucursales.*.required' => 'Indique la cantidad de cada sucursal.',
        'stockSucursales.*.min' => 'El stock de una sucursal no puede ser negativo.',
        'repuesto.producto_modelo_id.required_if' => 'Debe seleccionar un modelo para un repuesto.',
        'repuesto.tipo.required' => 'Debe seleccionar si es repuesto o accesorio.',
    ];

    #[On('openRepuestoEditModal')]
    public function openModal($id)
    {
        $repuesto = Repuesto::find($id);
        $this->repuesto = $repuesto->toArray();

        // `cantidad` viene en el toArray() y es un total derivado: se quita del
        // array para que ningun update() lo reescriba. El reparto real se carga
        // de la subtabla.
        unset($this->repuesto['cantidad']);
        $this->cargarStockSucursales($repuesto->id);

        $this->openModal = true;
    }

    public function update()
    {
        // Red de seguridad antes de validar: limpia tambien los accesorios
        // viejos, que se guardaron cuando el formulario aun pedia estos campos.
        $this->limpiarCamposDeAccesorio();

        $this->validate();

        DB::transaction(function () {
            $repuesto = Repuesto::find($this->repuesto['id']);
            $repuesto->update($this->repuesto);
            $this->guardarStockSucursales($repuesto->id);
        });
        $this->dispatch('refreshRepuestoTable');
        toastr()->success('Repuesto actualizado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset(['openModal', 'repuesto']);
    }

    public function render()
    {
        return view('livewire.repuesto.modals.repuesto-edit-modal', $this->catalogosDePieza() + [
            'sucursalesDisponibles' => $this->sucursalesDisponibles(),
            'sucursalesPorId' => $this->sucursalesPorId(),
        ]);
    }
}
