<?php

namespace App\Http\Controllers;

use App\Enums\RepuestoTipo;

class RepuestoController extends Controller
{
    /**
     * Las dos pantallas del catalogo comparten vista y componente, y solo
     * cambian el tipo. El tipo se decide AQUI, en el servidor, y no con un
     * filtro de la pantalla: es la ruta la que lo define.
     */
    public function index()
    {
        return view('app.repuesto.index', ['tipo' => RepuestoTipo::Repuesto->value]);
    }

    public function accesorios()
    {
        return view('app.repuesto.index', ['tipo' => RepuestoTipo::Accesorio->value]);
    }

    public function categorias()
    {
        return view('app.repuesto.categorias');
    }

    public function historial($repuesto_id)
    {
        return view('app.repuesto.historial-index', compact('repuesto_id'));
    }
}
