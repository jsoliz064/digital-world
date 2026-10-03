<?php

namespace App\Livewire\Compra;

use App\Enums\ProductoEstado;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\Compra;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;

class CompraTable extends DataTableComponent
{
    protected $model = Compra::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Proveedor", "proveedor.nombre")
                ->sortable()
                ->searchable(),
            Column::make("Fecha", "fecha_compra")
                ->sortable()
                ->searchable()
                ->format(
                    fn($value) => Carbon::parse($value)->format('d/m/Y')
                ),
            Column::make("Tipo de Cambio", "tipo_cambio")
                ->sortable()
                ->format(
                    fn($value) => 'Bs. ' . number_format($value, 2)
                ),
            Column::make("Costo Total", "costo_total")
                ->sortable()
                ->format(
                    fn($value) => '$ ' . number_format($value, 2)
                ),
            Column::make("Productos Vendidos", "id")
                ->sortable()
                ->format(function ($value, $row) {
                    return $row->productos->where('estado', ProductoEstado::Vendido->value)->count();
                }),
            Column::make("Productos Restantes", "id")
                ->sortable()
                ->format(function ($value, $row) {
                    return $row->productos->where('estado', '<>', ProductoEstado::Vendido->value)->count();
                }),
            Column::make("Cant. de Productos", "id")
                ->sortable()
                ->format(function ($value, $row) {
                    return $row->productos->count();
                }),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.compra.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshCompraTable')]
    public function refreshCompraTable()
    {
        $this->builder();
    }

    public function builder(): Builder
    {
        return Compra::query()
            ->orderBy('id', 'desc');
    }

    public function AgregarProductosCompra($id)
    {
        return redirect()->route('compras.productos', $id);
    }

    public function openCompraEditModal($id)
    {
        $this->dispatch('openCompraEditModal', $id);
    }

    public function openCompraDestroyModal($id)
    {
        $this->dispatch('openCompraDestroyModal', $id);
    }
}
