<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CompraRepuesto;
use Illuminate\Http\Request;

class CompraController extends Controller
{
    public function index()
    {
        return view('app.compra.index');
    }

    public function show($id)
    {
        $compra = Compra::findOrFail($id);
        return view('app.compra-lote.index', compact('compra'));
    }

    public function repuestoIndex()
    {
        return view('app.compra-repuesto.index');
    }

    public function repuestoCreate()
    {
        return view('app.compra-repuesto.create');
    }

    public function repuestoEditar($id)
    {
        $compraRepuesto = CompraRepuesto::find($id);
        return view('app.compra-repuesto.edit', compact('compraRepuesto'));
    }
}
