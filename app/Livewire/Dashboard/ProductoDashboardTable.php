<?php

namespace App\Livewire\Dashboard;

use App\Enums\ProductoEstado;
use App\Models\Producto;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;


class ProductoDashboardTable extends DataTableComponent
{
    protected $model = Producto::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Modelo", "modelo.nombre")
                ->sortable()
                ->searchable(),
            Column::make("Capacidad", "almacenamiento")
                ->sortable(),
            Column::make("Imei", "imei")
                ->sortable()
                ->searchable(),
            Column::make("Batería(%)", "bateria_porcentaje")
                ->sortable()
                ->format(function ($value) {
                    return $value . '%';
                }),
            Column::make("Color", "color")
                ->sortable(),
            Column::make("Estado", "estado")
                ->sortable()
                ->format(function ($value, $row) {
                    $color = ProductoEstado::colorDe($value);

                    $canChangeState = ProductoEstado::validatePermission($value);

                    // El color del tecnico pesa mas que el del estado: si el
                    // equipo esta en su banco, se quiere ver de quien es.
                    $reparacion = $row->ultimaReparacionPendiente();
                    if ($reparacion) {
                        $color = $reparacion->tecnico->color;
                    }

                    return view('livewire.producto.cambiar-estado-button', [
                        'estado' => $value,
                        'color' => $color,
                        'id' => $row->id,
                        'canChangeState' => $canChangeState,
                    ]);
                }),
        ];
    }

    public function builder(): Builder
    {
        return Producto::query()->select('productos.*')->whereNotIn('estado',  [ProductoEstado::Inventario->value, ProductoEstado::Oferta->value, ProductoEstado::Vendido->value, ProductoEstado::Roto->value, ProductoEstado::Transito->value]);
    }

    #[On('refreshProductoTable')]
    public function refreshProductoTable()
    {
        $this->builder();
    }

    public function cambiarEstado($id)
    {
        $this->dispatch('openProductoEstadoModal', $id);
    }
}
