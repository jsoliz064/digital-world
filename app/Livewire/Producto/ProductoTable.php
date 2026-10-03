<?php

namespace App\Livewire\Producto;

use App\Enums\ProductoEstado;
use App\Enums\ProductoVersion;
use App\Enums\ReparacionTipo;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\Sucursal;
use App\Models\VentaProducto;
use Carbon\Carbon;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;
use Illuminate\Database\Eloquent\Builder;

class ProductoTable extends DataTableComponent
{
    protected $model = Producto::class;
    public $selectedModelId = null;

    protected $listeners = ['modelSelected', 'modelCleared'];

    public function modelSelected($modelId)
    {
        $this->selectedModelId = $modelId;
        $this->resetPage(); // Reset pagination when model changes
    }

    public function modelCleared()
    {
        $this->selectedModelId = null;
        $this->resetPage(); // Reset pagination when clearing filter
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');

        // Las columnas con ->label() se excluyen del SELECT que arma el paquete,
        // así que los campos que usan sus callbacks hay que pedirlos aquí.
        $this->setAdditionalSelects([
            'productos.costo_unidad',
            'productos.costo_envio',
        ]);
    }

    public function columns(): array
    {
        return [
            Column::make("Modelo", "modelo.nombre")
                ->sortable()
                ->searchable(),
            Column::make("Capacidad", "almacenamiento")
                ->sortable(),
            Column::make("Version", "version")
                ->sortable()
                ->collapseOnTablet(),
            Column::make("Imei", "imei")
                ->sortable()
                ->searchable(),
            Column::make("Batería %", "bateria_porcentaje")
                ->sortable()
                ->format(function ($value) {
                    return $value . '%';
                })
                ->collapseOnTablet(),
            Column::make("Color", "color")
                ->sortable()
                ->collapseOnTablet(),
            Column::make("Grado", "estado_grado")
                ->sortable()
                ->collapseOnTablet(),
            Column::make("Detalles", "detalles")
                ->sortable()
                ->format(fn($value) => str($value)->limit(20))
                ->collapseOnTablet(),
            Column::make("Sucursal", "sucursal.nombre")
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
            Column::make("Detalle de Compra", "compra.id")
                ->format(function ($value, $row) {
                    $compra = Compra::find($value);
                    return "Lote {$compra->id}, {$compra->proveedor->nombre}, {$compra->fecha_compra}";
                })
                ->collapseOnTablet(),
            Column::make("Costo U+E")
                ->label(function ($row) {
                    $costo = $row->costo_unidad + $row->costo_envio;
                    return '$ ' . number_format($costo, 2);
                })
                ->collapseOnTablet(),
            Column::make("Costo Total", "costo_total")
                ->sortable()
                ->format(function ($value) {
                    return '$ ' . number_format($value, 2);
                })
                ->collapseOnTablet(),
            Column::make("Precio V.", "precio_vendedor")
                ->sortable()
                ->format(function ($value) {
                    return '$ ' . number_format($value, 2);
                })
                ->collapseOnTablet(),
            Column::make("Precio C.", "precio_cliente")
                ->sortable()
                ->format(function ($value) {
                    return '$ ' . number_format($value, 2);
                })
                ->collapseOnTablet(),
            Column::make("Detalle de Venta", "ventaProducto.id")
                ->format(function ($value, $row) {
                    $ventaProducto = VentaProducto::find($value);
                    $date = $ventaProducto ? Carbon::parse($ventaProducto->created_at) : null;
                    return $ventaProducto ? "Venta {$ventaProducto->id}, Precio: $ {$ventaProducto->subtotal}, {$date->format('Y-m-d')}" : "";
                })
                ->collapseOnTablet(),
            Column::make("Garantia")
                ->label(fn($row) => $this->distintivo((bool) $row->garantia_pendiente, 'Activa'))
                ->html()
                ->collapseOnTablet(),
            Column::make("Trabajo Externo")
                ->label(fn($row) => $this->distintivo((bool) $row->trabajo_externo_pendiente, 'En curso'))
                ->html()
                ->collapseOnTablet(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row) {
                    return view('livewire.producto.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    /** Distintivo de las columnas deducidas. Sin safelist: color en linea. */
    private function distintivo(bool $activo, string $texto): string
    {
        return $activo
            ? '<span style="color:green">' . $texto . '</span>'
            : '<span style="color:gray">-</span>';
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
                    // Un telefono en trabajo externo esta SIEMPRE 'Vendido', que
                    // el valor por defecto de este filtro oculta. Como los
                    // filtros se combinan con AND, sin esto el filtro de trabajo
                    // externo devolveria siempre cero resultados.
                    if ($this->getAppliedFilterWithValue('trabajo_externo')) {
                        return;
                    }
                    $builder->whereIn('estado', $values);
                })
                ->setFilterDefaultValue([ProductoEstado::Inventario->value, ProductoEstado::Oferta->value, ProductoEstado::Reparacion->value, ProductoEstado::Fuera->value, ProductoEstado::Roto->value, ProductoEstado::Transito->value]),
            MultiSelectDropdownFilter::make('Sucursal')
                ->options(
                    Sucursal::orderBy('nombre', 'asc')
                        ->get()
                        ->keyBy('id')
                        ->map(fn($sucursal) => $sucursal->nombre)
                        ->toArray()
                )
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('productos.sucursal_id', $values);
                }),
            MultiSelectDropdownFilter::make('Grado')
                ->options([
                    'A+' => 'A+',
                    'A' => 'A',
                    'AB' => 'AB',
                    'B' => 'B',
                    'C' => 'C',
                    'D' => 'D',
                ])
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('estado_grado', $values);
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
            // La clave del filtro es Str::snake del nombre: 'trabajo_externo'.
            // Es la que consulta el filtro de Estado para apartarse.
            MultiSelectDropdownFilter::make('Trabajo Externo', 'trabajo_externo')
                ->options([
                    'si' => 'Con trabajo externo',
                    'no' => 'Sin trabajo externo',
                ])
                ->filter(function (Builder $builder, array $values) {
                    if (empty($values) || count($values) === 2) {
                        return;
                    }
                    $conTrabajo = in_array('si', $values, true);
                    $builder->whereHas(
                        'reparaciones',
                        fn($query) => $query
                            ->where('tipo', ReparacionTipo::Externo->value)
                            ->where('estado', 'Pendiente'),
                        $conTrabajo ? '>=' : '<',
                        1
                    );
                }),
        ];
    }

    public function builder(): Builder
    {
        return Producto::query()
            // Los distintivos se DEDUCEN de si hay una reparacion pendiente de
            // ese tipo, en vez de leer una bandera guardada. La bandera
            // `garantia_activa` solo se apagaba desde su propio modal, asi que
            // cerrar la reparacion desde la pantalla del tecnico la dejaba
            // encendida para siempre. Una subconsulta por columna, sin N+1.
            ->withExists([
                'reparaciones as garantia_pendiente' => fn($query) => $query
                    ->where('tipo', ReparacionTipo::Garantia->value)
                    ->where('estado', 'Pendiente'),
                'reparaciones as trabajo_externo_pendiente' => fn($query) => $query
                    ->where('tipo', ReparacionTipo::Externo->value)
                    ->where('estado', 'Pendiente'),
            ])
            ->when($this->selectedModelId, function ($query) {
                $query->where('producto_modelo_id', $this->selectedModelId);
            })->orderby('productos.created_at', 'desc');
    }


    #[On('refreshProductoTable')]
    public function refreshProductoTable()
    {
        $this->builder();
    }

    public function changeVentaRapida($id)
    {
        $producto = Producto::find($id);
        $producto->venta_rapida = !$producto->venta_rapida;
        $producto->save();
    }

    public function cambiarEstado($id)
    {
        $producto = Producto::find($id);
        if ($producto->estado == ProductoEstado::Reparacion->value) {
            $reparacion = $producto->ultimaReparacionPendiente();
            $this->dispatch('openReparacionEditModal', $reparacion->id);
        } else {
            $this->dispatch('openProductoEstadoModal', $id);
        }
    }

    public function openProductoEditModal($id)
    {
        $producto = Producto::find($id);
        if (!$producto->estaDisponible() && $producto->estado !== ProductoEstado::Transito->value) {
            toastr()->error('El producto debe estar en inventario o transito');
            return;
        }
        $this->dispatch('openCompraLoteProductoEditModal', $id);
    }

    public function openProductoGarantiaModal($id)
    {
        $producto = Producto::find($id);
        if ($producto->estado !== ProductoEstado::Vendido->value) {
            toastr()->error('El producto debe estar vendido');
            return;
        }
        $this->dispatch('openProductoGarantiaModal', $id);
    }

    public function openProductoTrabajoExternoModal($id)
    {
        $producto = Producto::find($id);

        // Misma comprobacion que en el menu, repetida en el servidor: el @if
        // del blade solo esconde el boton.
        if ($producto->estado !== ProductoEstado::Vendido->value) {
            toastr()->error('El producto debe estar vendido');
            return;
        }

        $this->dispatch('openProductoTrabajoExternoModal', $id);
    }

    public function openProductoDestroyModal($id)
    {
        $producto = Producto::find($id);
        if (!$producto->estaDisponible()) {
            toastr()->error('El producto debe estar en el inventario');
            return;
        }
        $this->dispatch('openProductoDestroyModal', $id);
    }

    public function openProductoHistorial($id)
    {
        return redirect()->route('productos.historial', $id);
    }
}
