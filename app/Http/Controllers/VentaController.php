<?php

namespace App\Http\Controllers;

use App\Models\Venta;

/** Ventas: una sola para equipos, repuestos y accesorios. */
class VentaController extends Controller
{
    public function index()
    {
        return view('app.venta.index');
    }

    public function detalles($id)
    {
        $venta = Venta::findOrFail($id);

        return view('app.venta.detalles', compact('venta'));
    }

    /**
     * La nota de venta para la termica de 80 mm: HTML suelto, sin layout, que
     * se imprime solo al abrirse. Lo comprado se agrupa con su equipo.
     */
    public function nota($id)
    {
        $venta = Venta::with([
            'sucursal', 'user', 'fichaCliente',
            'detalles' => fn($q) => $q->orderBy('id'),
            'detalles.producto.modelo', 'detalles.repuesto', 'detalles.accesorio',
            'pagos' => fn($q) => $q->orderBy('id'),
            'pagos.metodo', 'pagos.producto.modelo',
        ])->findOrFail($id);

        $equipos = $venta->detalles->whereNotNull('producto_id');
        $sueltos = $venta->detalles->whereNull('producto_id')
            ->filter(fn($d) => !$d->producto_asociado_id || !$equipos->contains('producto_id', $d->producto_asociado_id));

        return view('app.venta.nota', compact('venta', 'equipos', 'sueltos'));
    }

    public function create()
    {
        return view('app.venta.create');
    }

    public function editar($id)
    {
        // findOrFail: con un id inexistente, un 404 limpio y no un error de Livewire.
        $venta = Venta::findOrFail($id);

        return view('app.venta.edit', compact('venta'));
    }
}
