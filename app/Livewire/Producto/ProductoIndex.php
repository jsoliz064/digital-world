<?php

namespace App\Livewire\Producto;

use App\Enums\ProductoEstado;
use App\Exports\ProductoModeloExport;
use App\Models\Producto;
use App\Models\Sucursal;
use Livewire\Component;

class ProductoIndex extends Component
{
    public $sucursalesResumen = [];
    public $totalProductos = 0;
    public $totalInventario = 0;
    public $totalInventarioVendido = 0;

    public function mount()
    {
        $this->loadResumen();
        $productos = Producto::selectRaw("
            costo_total,
            case when estado = 'Roto' then costo_total + 60 else precio_vendedor end as precio_vendedor
        ")
            ->where('estado', '!=', ProductoEstado::Vendido->value)
            ->get();
        $this->totalProductos = $productos->count();
        $this->totalInventario = $productos->sum('costo_total');
        $this->totalInventarioVendido = $productos->sum('precio_vendedor');
    }

    /**
     * Cuantos productos no vendidos tiene cada sucursal, por estado.
     *
     * El conteo se hace en SQL. Antes era un eager load de la relacion completa
     * y un groupBy() de Collection: traia a PHP TODAS las filas de productos no
     * vendidos -- con sus treinta columnas, imagenes incluidas en el modelo --
     * solo para contarlas. Son dos consultas agregadas y ningun modelo en
     * memoria.
     *
     * Se muestran las sucursales activas y, de las desactivadas, solo las que
     * todavia guardan equipos: esos equipos existen y no pueden desaparecer del
     * resumen. Antes habia un `where('id', '<>', 5)` sin explicacion que en la
     * base vieja no excluia nada; en la nueva habria escondido la quinta
     * sucursal real que diera de alta el negocio.
     */
    public function loadResumen()
    {
        $conteos = Producto::query()
            ->where('estado', '!=', ProductoEstado::Vendido->value)
            ->selectRaw('sucursal_id, estado, COUNT(*) as total')
            ->groupBy('sucursal_id', 'estado')
            ->get()
            ->groupBy('sucursal_id');

        // Ordenado por nombre: el filtro de sucursal de ProductoTable ya usa
        // orderBy('nombre') y este card no, asi que las dos listas salian en
        // orden distinto.
        $this->sucursalesResumen = Sucursal::orderBy('nombre')
            ->get()
            ->filter(fn($sucursal) => $sucursal->activa || isset($conteos[$sucursal->id]))
            ->values()
            ->map(fn($sucursal) => [
                'nombre' => $sucursal->nombre,
                'resumen' => ($conteos[$sucursal->id] ?? collect())
                    ->pluck('total', 'estado')
                    ->map(fn($total) => (int) $total),
            ]);
    }

    public function render()
    {
        return view('livewire.producto.producto-index');
    }

    public function openProductoCreateModal()
    {
        $this->dispatch('openProductoCreateModal');
    }

    public function openProductoEditSucursalModal()
    {
        $this->dispatch('openProductoEditSucursalModal');
    }

    public function openProductoEstadoMasivoModal()
    {
        $this->dispatch('openProductoEstadoMasivoModal');
    }

    public function exportProductosExcel()
    {
        $now = date('Hi');
        return (new ProductoModeloExport())
            ->download("productos-modelos-{$now}.xlsx");
    }
}
