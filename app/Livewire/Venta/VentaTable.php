<?php

namespace App\Livewire\Venta;

use App\Models\Venta;
use Carbon\Carbon;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;

class VentaTable extends DataTableComponent
{
    protected $model = Venta::class;

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
            Column::make("ID", "id")
                ->sortable()
                ->searchable(),
            Column::make("Sucursal", "sucursal.nombre")
                ->sortable(),
            Column::make("Cliente")
                ->sortable()
                ->format(fn($value, $row) => $row->nombreCliente()),
            Column::make("Subtotal", "subtotal")
                ->searchable()
                ->format(fn($value) => '$ ' . number_format($value, 2)),
            Column::make("Descuento", "descuento")
                ->sortable()
                ->format(fn($value) => '$ ' . number_format($value, 2)),
            Column::make("Total", "total")
                ->sortable()
                ->format(fn($value) => '$ ' . number_format($value, 2)),
            Column::make("Total (Bs)", "total_bs")
                ->sortable()
                ->format(fn($value) => 'Bs. ' . number_format($value, 2)),
            Column::make("Cant. Productos", "id")
                ->sortable()
                ->format(function ($value, $row) {
                    return $row->detalles()->count();
                })->searchable(),
            Column::make("Fecha", "created_at")
                ->sortable(),
            Column::make("Vendedor", "user.name")
                ->sortable()
                ->searchable(),
            Column::make('Acciones', 'id')
                ->format(fn($value, $row) => view('livewire.venta.actions-buttons', ['row' => $row])),
        ];
    }

    public function builder(): Builder
    {
        // La ficha, cargada de una vez: nombreCliente() la consulta por fila sin
        // esto, y son diez filas por pagina.
        return Venta::query()
            ->with('fichaCliente:id,nombre')
            ->when($this->fechaDesde, function ($query) {
                $query->where('ventas.created_at', '>=', Carbon::parse($this->fechaDesde)->startOfDay());
            })
            ->when($this->fechaHasta, function ($query) {
                $query->where('ventas.created_at', '<=', Carbon::parse($this->fechaHasta)->endOfDay());
            })
            ->when(!empty($this->users), function ($query) {
                $query->whereIn('ventas.user_id', $this->users);
            })
            ->when(!empty($this->sucursales), function ($query) {
                $query->whereIn('ventas.sucursal_id', $this->sucursales);
            })
            ->orderby('ventas.created_at', 'desc');
    }

    #[On('refreshVentaTable')]
    public function refreshVentaTable()
    {
        $this->builder();
    }

    public function openVentaDetalleModal($id)
    {
        return redirect(route('ventas.detalles', $id));
    }
}
