<?php

namespace App\Livewire\Venta;

use App\Models\Sucursal;
use App\Models\VentaProducto;
use Carbon\Carbon;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;

class VentaProductoTable extends DataTableComponent
{
    protected $model = VentaProducto::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Venta ID", "venta_id")
                ->sortable()
                ->searchable(),
            Column::make("Sucursal", "sucursal.nombre")
                ->sortable(),
            Column::make("Producto", "id")
                ->format(function ($value, $row) {
                    $row->refresh();
                    return $row->producto->descripcion;
                })
                ->searchable(),
            Column::make("Precio", "precio")
                ->sortable()
                ->format(function ($value) {
                    return '$ ' . number_format($value, 2);
                }),
            Column::make("Descuento", "descuento")
                ->sortable()
                ->format(function ($value) {
                    return '$ ' . number_format($value, 2);
                }),
            Column::make("Total", "subtotal")
                ->sortable()
                ->format(function ($value) {
                    return '$ ' . number_format($value, 2);
                }),
            Column::make("TC", "tipo_cambio")
                ->sortable()
                ->format(function ($value) {
                    return 'Bs. ' . number_format($value, 2);
                }),
            Column::make("Total (Bs)", "subtotal_bs")
                ->sortable()
                ->format(function ($value) {
                    return 'Bs. ' . number_format($value, 2);
                }),
            Column::make("Usuario", "id")
                ->sortable()
                ->format(function ($value, $row) {
                    return $row->venta->user->name;
                })
                ->searchable(),
            // La clave era 'id': ordenaba y buscaba por ventas_productos.id, no por
            // el cliente, asi que las dos cosas estaban mintiendo. Ahora apunta a
            // la columna de la relacion, que el paquete une solo.
            Column::make("Cliente", "venta.cliente")
                ->label(fn($row) => $row->venta?->nombreCliente() ?? '—')
                ->setCustomSlug('cliente')
                ->sortable()
                ->searchable(),
            Column::make("Fecha", "created_at")
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            DateFilter::make('Desde')
                ->filter(function (Builder $builder, string $value) {
                    $fecha = new Carbon($value);
                    $builder->where('created_at', '>=', $fecha->copy()->startOfDay());
                }),
            DateFilter::make('Hasta')
                ->filter(function (Builder $builder, string $value) {
                    $fecha = new Carbon($value);
                    $builder->where('created_at', '<=', $fecha->copy()->endOfDay());
                }),
            MultiSelectDropdownFilter::make('Sucursal')
                ->options(
                    Sucursal::orderBy('nombre', 'asc')
                        ->get()
                        ->keyBy('id')
                        ->map(fn($sucursal) => $sucursal->nombre)
                        ->toArray()
                )
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('sucursal_id', $values);
                }),
        ];
    }

    public function builder(): Builder
    {
        return VentaProducto::query()->orderby('created_at', 'desc');
    }
}
