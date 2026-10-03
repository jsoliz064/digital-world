<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\VentaRepuesto;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function index()
    {
        return view('app.venta.index');
    }

    public function detalles($id)
    {
        $venta = Venta::find($id);
        return view('app.venta.detalles', compact('venta'));
    }

    public function create()
    {
        return view('app.venta.create');
    }

    public function editar($id)
    {
        // findOrFail y no find: con un id inexistente conviene un 404 limpio y
        // no un error de Livewire mas abajo al montar el componente.
        $venta = Venta::findOrFail($id);
        return view('app.venta.edit', compact('venta'));
    }

    public function repuestoIndex()
    {
        return view('app.venta-repuesto.index');
    }
    
    public function repuestoCreate()
    {
        return view('app.venta-repuesto.create');
    }

    public function repuestoEditar($id)
    {
        $ventaRepuesto = VentaRepuesto::find($id);
        return view('app.venta-repuesto.edit', compact('ventaRepuesto'));
    }
}
