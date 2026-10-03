<?php

namespace App\Livewire\VentaRepuesto;

use App\Enums\RepuestoTipo;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\VentaRepuesto;
use Carbon\Carbon;
use Livewire\Component;

class VentaRepuestoIndex extends Component
{

    public $fechaDesde = '';
    public $fechaHasta = '';
    public array $selectedUsers = [];
    public array $selectedSucursales = [];

    public float $totalVenta = 0;
    public int $cantidadRepuestos = 0;
    public int $cantidadAccesorios = 0;
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
    
    public function render()
    {
        return view('livewire.venta-repuesto.venta-repuesto-index');
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
        $query = VentaRepuesto::query()
            ->when($this->fechaDesde, fn($q) => $q->where('ventas_repuestos.created_at', '>=', Carbon::parse($this->fechaDesde)->startOfDay()))
            ->when($this->fechaHasta, fn($q) => $q->where('ventas_repuestos.created_at', '<=', Carbon::parse($this->fechaHasta)->endOfDay()))
            ->when(!empty($this->selectedUsers), fn($q) => $q->whereIn('ventas_repuestos.user_id', $this->selectedUsers))
            ->when(!empty($this->selectedSucursales), fn($q) => $q->whereIn('ventas_repuestos.sucursal_id', $this->selectedSucursales));

        $this->totalVenta = (clone $query)->sum('total');
        $totalCosto = (clone $query)->sum('costo_total');
        $this->totalGanancia = $this->totalVenta - $totalCosto;

        $this->cantidadVentas = (clone $query)->count();

        $detailsQuery = (clone $query)->join('ventas_repuestos_detalles', 'ventas_repuestos.id', '=', 'ventas_repuestos_detalles.venta_repuesto_id');

        // Se lee el `tipo` congelado en la linea, no el del catalogo: asi la
        // cifra de un periodo no cambia si manana se reclasifica un articulo.
        $porTipo = (clone $detailsQuery)
            ->select('ventas_repuestos_detalles.tipo')
            ->selectRaw('SUM(ventas_repuestos_detalles.cantidad) as unidades')
            ->groupBy('ventas_repuestos_detalles.tipo')
            ->pluck('unidades', 'tipo');

        $this->cantidadRepuestos = (int) ($porTipo[RepuestoTipo::Repuesto->value] ?? 0);
        $this->cantidadAccesorios = (int) ($porTipo[RepuestoTipo::Accesorio->value] ?? 0);
    }

    public function ventaRepuestoCreate()
    {
        return redirect()->route('ventas.repuestos.crear');
        // $this->dispatch('openVentaRepuestoCreateModal');
    }
}
