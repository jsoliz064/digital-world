<?php

namespace App\Livewire\Dashboard;

use App\Enums\ProductoEstado;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaProducto;
use Livewire\Component;

class DashboardIndex extends Component
{
    public $ventas_dia = 0;
    public $ventas_dia_cant = 0;
    public $ventas_mes = 0;
    public $ventas_mes_cant = 0;
    public $productos_inventario = 0;
    public $productos_oferta = 0;
    public $productos_reparacion = 0;


    public function mount()
    {
        // El de inventario cuenta TODO lo vendible, Oferta incluida: es la
        // cifra que el tablero siempre mostro y no cambia por este estado. El
        // de oferta es un subconteo de ese, no una cifra aparte que se sume.
        $this->productos_inventario = Producto::disponibles()->count();
        $this->productos_oferta = Producto::where('estado', ProductoEstado::Oferta->value)->count();
        $this->productos_reparacion = Producto::where('estado', ProductoEstado::Reparacion)->count();

        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $this->ventas_dia = VentaProducto::whereBetween('created_at', [$startOfDay, $endOfDay])->sum('subtotal');
        $this->ventas_dia_cant = VentaProducto::whereBetween('created_at', [$startOfDay, $endOfDay])->count();
        
        $this->ventas_mes = VentaProducto::whereBetween('created_at', [$startOfMonth, $endOfMonth])->sum('subtotal');
        $this->ventas_mes_cant = VentaProducto::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard-index');
    }
}
