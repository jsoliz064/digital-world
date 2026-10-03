<?php

namespace App\Livewire\CompraRepuesto;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\CompraRepuesto;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;

class CompraRepuestoTable extends DataTableComponent
{
    protected $model = CompraRepuesto::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Fecha", "fecha_compra")
                ->sortable()
                ->searchable()
                ->format(
                    fn($value) => Carbon::parse($value)->format('d/m/Y')
                ),
            Column::make("Costo Total", "costo_total")
                ->sortable()
                ->format(
                    fn($value) => '$ ' . number_format($value, 2)
                ),
            Column::make("Tipo de Cambio", "tipo_cambio")
                ->sortable(),
            Column::make("Cant. de Repuestos", "cantidad_repuestos")
                ->sortable(),
            Column::make("Usuario", "user.name")
                ->sortable()
                ->searchable(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.compra-repuesto.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    public function builder(): Builder
    {
        return CompraRepuesto::query()
            ->orderby('compras_repuestos.created_at', 'desc');
    }

    #[On('refreshCompraRepuestoTable')]
    public function refreshCompraRepuestoTable()
    {
        $this->builder();
    }

    public function openCompraEdit($id)
    {
        return redirect()->route('compras.repuestos.editar', $id);
    }

    public function openCompraRepuestoDestroyModal($id)
    {
        $this->dispatch('openCompraRepuestoDestroyModal', $id);
    }
}
