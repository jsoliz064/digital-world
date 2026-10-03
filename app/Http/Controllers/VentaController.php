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
