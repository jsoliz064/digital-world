<?php

namespace App\Livewire\Tecnico\Modals;

use App\Models\Tecnicos;
use Livewire\Component;
use Livewire\Attributes\On;

class TecnicoEditModal extends Component
{

    public $openModal = false;
    public $tecnico = [];

    protected $rules = [
        'tecnico.nombre' => 'required|string|max:255',
        'tecnico.color' => 'nullable|string|max:10',
        'tecnico.comision_porcentaje' => 'required|numeric|min:0|max:100',
    ];

    protected $messages = [
        'tecnico.nombre' => 'Debe ingresar un nombre',
        'tecnico.comision_porcentaje.required' => 'Debe ingresar el porcentaje de comisión',
        'tecnico.comision_porcentaje.numeric' => 'La comisión debe ser un número',
        'tecnico.comision_porcentaje.min' => 'La comisión no puede ser negativa',
        'tecnico.comision_porcentaje.max' => 'La comisión no puede pasar de 100',
    ];

    #[On('openTecnicoEditModal')]
    public function openModal($id)
    {
        $this->tecnico = Tecnicos::findOrFail($id)->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();

        $tecnico = Tecnicos::find($this->tecnico['id']);
        $tecnico->nombre = $this->tecnico['nombre'];
        $tecnico->color = isset($this->tecnico['color']) ? $this->tecnico['color'] : null;
        $tecnico->comision_porcentaje = $this->tecnico['comision_porcentaje'];
        $tecnico->save();
        $this->dispatch('refreshTecnicoTable');
        toastr()->success('Técnico editado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.tecnico.modals.tecnico-edit-modal');
    }
}
