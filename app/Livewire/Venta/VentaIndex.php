<?php

namespace App\Livewire\Venta;

use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Livewire\Component;

class VentaIndex extends Component
{
    public $fechaDesde = '';
    public $fechaHasta = '';
    public array $selectedUsers = [];
    public array $selectedSucursales = [];

    public float $totalVentaUsd = 0;
    public float $totalVentaBs = 0;
    public int $cantidadProductos = 0;
    public int $cantidadVentas = 0;
    public float $totalGanancia = 0;

    public $users = [];
    public $sucursales = [];

    public function mount()
    {
        $this->users = User::orderBy('name')->get();
        $this->sucursales = Sucursal::orderBy('nombre')->get();
        $this->applyFilters();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['fechaDesde', 'fechaHasta', 'selectedUsers.0', 'selectedSucursales.0'])) {
            $this->applyFilters();
        }
    }

    public function applyFilters()
    {
        $this->calculateTotals();

        $this->dispatch('filtersUpdated', [
            'fechaDesde' => $this->fechaDesde,
            'fechaHasta' => $this->fechaHasta,
            'users' => $this->selectedUsers,
            'sucursales' => $this->selectedSucursales,
        ]);
    }

    public function calculateTotals()
    {
        $query = Venta::query()
            ->when($this->fechaDesde, fn($q) => $q->where('ventas.created_at', '>=', Carbon::parse($this->fechaDesde)->startOfDay()))
            ->when($this->fechaHasta, fn($q) => $q->where('ventas.created_at', '<=', Carbon::parse($this->fechaHasta)->endOfDay()))
            ->when(!empty($this->selectedUsers), fn($q) => $q->whereIn('user_id', $this->selectedUsers))
            ->when(!empty($this->selectedSucursales), fn($q) => $q->whereIn('ventas.sucursal_id', $this->selectedSucursales));

        $this->totalVentaUsd = (clone $query)->sum('total');
        $this->totalVentaBs = (clone $query)->sum('total_bs');
        $this->cantidadVentas = (clone $query)->count();

        $detailsQuery = (clone $query)->join('ventas_productos', 'ventas.id', '=', 'ventas_productos.venta_id');

        $this->cantidadProductos = (clone $detailsQuery)->count('ventas_productos.id');

        $totalCosto = (clone $detailsQuery)->sum('ventas_productos.costo');

        $this->totalGanancia = $this->totalVentaUsd - $totalCosto;
    }

    public function render()
    {
        return view('livewire.venta.venta-index');
    }

    public function ventaCreate()
    {
        return redirect()->route('ventas.crear');
    }
}
