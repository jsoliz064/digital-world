<?php

namespace App\Livewire\Venta;

use App\Models\VentaProducto;
use Carbon\Carbon;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;

class VentaDetalleTable extends DataTableComponent
{
    protected $model = VentaProducto::class;
    public $venta;

    public function mount($venta)
    {
        $this->venta = $venta;
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("ID", "id")
                ->sortable()
                ->searchable(),
            Column::make("Producto", "producto.descripcion")
                ->sortable()
                ->searchable(),
            Column::make("Precio ($)", "precio")
                ->sortable()
                ->format(fn($value) => '$ ' . number_format($value, 2)),
            Column::make("Descuento ($)", "descuento")
                ->sortable()
                ->format(fn($value) => '$ ' . number_format($value, 2)),
            Column::make("Subtotal ($)", "subtotal")
                ->sortable()
                ->format(fn($value) => '$ ' . number_format($value, 2)),
            Column::make("Subtotal (Bs)", "subtotal_bs")
                ->sortable()
                ->format(fn($value) => 'Bs. ' . number_format($value, 2)),
            Column::make("Garantía (Meses)", "garantia_meses")
                ->sortable()
                ->format(fn($value) => $value ? $value . ' meses' : 'N/A'),
            Column::make("Expira Garantía", "garantia_fecha_exp")
                ->sortable()
                ->format(fn($value) => $value ? Carbon::parse($value)->format('d/m/Y') : 'N/A'),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.venta.actions-detalle-buttons', [
                        'row' => $row
                    ]);
                })
        ];
    }

    public function builder(): Builder
    {
        return VentaProducto::query()
            ->where('venta_id', $this->venta->id);
    }

    #[On('refreshVentaDetalleTable')]
    public function refreshVentaDetalleTable()
    {
        $this->builder();
    }

    public function openVentaDetalleDestroyModal($id)
    {
        $this->dispatch('openVentaDetalleDestroyModal', $id);
    }
}
