<?php

namespace App\Livewire\VentaRepuesto;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\VentaRepuesto;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;

class VentaRepuestoTable extends DataTableComponent
{
    protected $model = VentaRepuesto::class;

    public string $fechaDesde = '';
    public string $fechaHasta = '';
    public array $users = [];
    public array $sucursales = [];

    #[On('filtersUpdated')]
    public function updateTableFilters($filters)
    {
        $this->fechaDesde = $filters['fechaDesde'] ?? '';
        $this->fechaHasta = $filters['fechaHasta'] ?? '';
        $this->users = $filters['users'] ?? [];
        $this->sucursales = $filters['sucursales'] ?? [];

        $this->setBuilder($this->builder());
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Fecha", "created_at")
                ->sortable()
                ->searchable(),
            Column::make("Total", "total")
                ->sortable()
                ->format(
                    fn($value) => '$ ' . number_format($value, 2)
                ),
            Column::make("Ajuste (Bs)", "ajuste_bs")
                ->sortable()
                ->format(function ($value) {
                    if (abs((float) $value) < 0.01) {
                        return '<span class="text-gray-300">—</span>';
                    }

                    $color = $value > 0 ? 'text-green-600' : 'text-red-600';
                    $signo = $value > 0 ? '+' : '';

                    return '<span class="font-semibold ' . $color . '">' . $signo . number_format((float) $value, 2) . '</span>';
                })
                ->html()
                ->collapseOnTablet(),
            Column::make("Articulos", "cantidad_repuestos")
                ->sortable(),
            Column::make("Cliente", "fichaCliente.nombre")
                ->sortable(),
            Column::make("Origen", "venta.id")
                ->format(function ($value) {
                    if (!$value) {
                        return '<span class="text-gray-400">—</span>';
                    }

                    return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold '
                        . 'bg-green-100 text-green-800">Venta #' . e($value) . '</span>';
                })
                ->html(),
            Column::make("Usuario", "user.name")
                ->sortable()
                ->searchable(),
            Column::make("Sucursal", "sucursal.nombre")
                ->sortable()
                ->searchable(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.venta-repuesto.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    public function builder(): Builder
    {
        return VentaRepuesto::query()
            ->with('fichaCliente:id,nombre')
            ->when($this->fechaDesde, function ($query) {
                $query->where('ventas_repuestos.created_at', '>=', Carbon::parse($this->fechaDesde)->startOfDay());
            })
            ->when($this->fechaHasta, function ($query) {
                $query->where('ventas_repuestos.created_at', '<=', Carbon::parse($this->fechaHasta)->endOfDay());
            })
            ->when(!empty($this->users), function ($query) {
                $query->whereIn('ventas_repuestos.user_id', $this->users);
            })
            ->when(!empty($this->sucursales), function ($query) {
                $query->whereIn('ventas_repuestos.sucursal_id', $this->sucursales);
            })
            ->orderby('ventas_repuestos.created_at', 'desc');
    }

    #[On('refreshVentaRepuestoTable')]
    public function refreshVentaRepuestoTable()
    {
        $this->builder();
    }

    public function openVentaEdit($id)
    {
        return redirect()->route('ventas.repuestos.editar', $id);
    }

    public function openVentaRepuestoDestroyModal($id)
    {
        $this->dispatch('openVentaRepuestoDestroyModal', $id);
    }
}
