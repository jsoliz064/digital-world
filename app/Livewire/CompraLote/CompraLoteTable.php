<?php

namespace App\Livewire\CompraLote;

use App\Enums\ProductoEstado;
use App\Enums\ProductoVersion;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\Sucursal;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

class CompraLoteTable extends DataTableComponent
{
    protected $model = Producto::class;
    public $compraId; // Add this property

    public function mount($compraId)
    {
        $this->compraId = is_numeric($compraId) ? (int) $compraId : null;
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
        // $this->setDefaultSort('created_at', 'desc');
    }

    public function filters(): array
    {
        return [
            MultiSelectDropdownFilter::make('Estado')
                ->options(ProductoEstado::toSelectArray()->toArray())
                ->filter(function (Builder $builder, array $values) {
                    if (empty($values)) {
                        return;
                    }
                    $builder->whereIn('estado', $values);
                })
                ->setFilterDefaultValue(['Inventario', 'Oferta', 'Reparacion', 'Fuera', 'Roto']),
            MultiSelectDropdownFilter::make('Modelos')
                ->options(
                    ProductoModelo::orderBy('nombre', 'asc')
                        ->get()
                        ->keyBy('id')
                        ->map(fn($modelo) => $modelo->nombre)
                        ->toArray()
                )
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('producto_modelo_id', $values);
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
            MultiSelectDropdownFilter::make('Versión')
                ->options(
                    collect(ProductoVersion::cases())
                        ->mapWithKeys(fn($case) => [$case->value => $case->value])
                        ->toArray()
                )
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('version', $values);
                }),
        ];
    }

    public function columns(): array
    {
        return [

            Column::make("ID", "id")
                ->sortable(),
            Column::make("Modelo", "modelo.nombre")
                ->sortable()
                ->searchable(),
            Column::make("Capacidad", "almacenamiento")
                ->sortable(),
            Column::make("Version", "version")
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
            Column::make("Precio V.", "precio_vendedor")
                ->sortable()
                ->format(function ($value) {
                    return '$ ' . number_format($value, 2);
                }),
            Column::make("Precio C.", "precio_cliente")
                ->sortable()
                ->format(function ($value) {
                    return '$ ' . number_format($value, 2);
                }),
            Column::make("Registrado", "created_at")
                ->sortable(),
            Column::make("Sucursal", "sucursal.nombre")
                ->sortable(),
            Column::make("Fecha Venta.", "id")
                ->sortable()
                ->format(function ($value, $row) {
                    return $row->ventaProducto ? $row->ventaProducto->created_at : '-';
                }),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.compra-lote.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshProductoTable')]
    public function refreshCompraLoteTable()
    {
        $this->builder();
    }

    public function openCompraLoteProductoEditModal($id)
    {
        $producto = Producto::find($id);
        if (!$producto->estaDisponible() && $producto->estado !== ProductoEstado::Transito->value) {
            toastr()->error('El producto debe estar en el inventario o en transito');
            return;
        }
        $this->dispatch('openCompraLoteProductoEditModal', $id);
    }

    public function openCompraLoteProductoDestroyModal($id)
    {
        $producto = Producto::find($id);
        if (!$producto->estaDisponible()) {
            toastr()->error('El producto debe estar en el inventario');
            return;
        }
        $this->dispatch('openCompraLoteProductoDestroyModal', $id);
    }

    public function cambiarEstado($id)
    {
        $this->dispatch('openProductoEstadoModal', $id);
    }

    public function builder(): Builder
    {

        if (!$this->compraId) {
            return Producto::query()->where('id', -1);
        }

        return Producto::query()
            ->where('compra_id', $this->compraId)
            ->orderBy('created_at', 'desc');
    }
}
