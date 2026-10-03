<?php

namespace App\Livewire\Tecnico\Modals;

use App\Models\Tecnicos;
use Livewire\Component;
use Livewire\Attributes\On;


class TecnicoCreateModal extends Component
{
    public $openModal = false;
    public $tecnico = [];

    protected $rules = [
        'tecnico.nombre' => 'required|string|max:255',
        'tecnico.color' => 'required|string|max:10',
        'tecnico.comision_porcentaje' => 'required|numeric|min:0|max:100',
    ];

    protected $messages = [
        'tecnico.nombre' => 'Debe ingresar un nombre',
        'tecnico.color.required' => 'Debe seleccionar un color',
        'tecnico.comision_porcentaje.required' => 'Debe ingresar el porcentaje de comisión',
        'tecnico.comision_porcentaje.numeric' => 'La comisión debe ser un número',
        'tecnico.comision_porcentaje.min' => 'La comisión no puede ser negativa',
        'tecnico.comision_porcentaje.max' => 'La comisión no puede pasar de 100',
    ];

    #[On('openTecnicoCreateModal')]
    public function openModal()
    {
        $this->openModal = true;
        $this->tecnico['color'] = '#000000';
        // 50/50 de la mano de obra: el acuerdo por defecto del negocio.
        $this->tecnico['comision_porcentaje'] = 50;
    }

    public function store()
    {
        $this->validate();
        Tecnicos::create($this->tecnico);
        $this->dispatch('refreshTecnicoTable');
        toastr()->success('Técnico creado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.tecnico.modals.tecnico-create-modal');
    }
}
