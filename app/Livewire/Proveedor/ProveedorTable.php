<?php

namespace App\Livewire\Proveedor;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\Proveedor;
use Livewire\Attributes\On;


class ProveedorTable extends DataTableComponent
{
    protected $model = Proveedor::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Nombre", "nombre")
                ->sortable()
                ->searchable(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.proveedor.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }
    #[On('refreshProveedorTable')]
    public function refreshProveedorTable()
    {
        $this->builder();
    }

    public function openProveedorEditModal($id)
    {
        $this->dispatch('openProveedorEditModal', $id);
    }

    public function openProveedorDestroyModal($id)
    {
        $this->dispatch('openProveedorDestroyModal', $id);
    }
}
