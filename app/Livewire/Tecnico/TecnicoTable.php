<?php

namespace App\Livewire\Tecnico;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\Tecnicos;
use Livewire\Attributes\On;


class TecnicoTable extends DataTableComponent
{
    protected $model = Tecnicos::class;

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
            Column::make("Color", "color")
                ->format(function ($value, $row) {
                    return $row->getDivColor();
                })
                ->html(),
            Column::make("% Comisión", "comision_porcentaje")
                ->sortable()
                ->format(fn($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . ' %'),
            Column::make("Productos Pendientes", "id")
                ->sortable()
                ->format(function ($value, $row, Column $column) {
                    return count($row->reparaciones->where('estado', 'Pendiente'));
                }),
            Column::make("Deuda Pendiente", "id")
                ->sortable()
                ->format(function ($value, $row, Column $column) {
                    return 'Bs.' . $row->reparaciones->where('estado', 'Terminado')->where('pagado', false)->sum('costo');
                }),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.tecnico.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshTecnicoTable')]
    public function refreshTecnicoTable()
    {
        $this->builder();
    }

    public function openTecnicoEditModal($id)
    {
        $this->dispatch('openTecnicoEditModal', $id);
    }

    public function openTecnicoDestroyModal($id)
    {
        $this->dispatch('openTecnicoDestroyModal', $id);
    }
}
