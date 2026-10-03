<?php

namespace App\Livewire\ProductoHistorial;

use App\Enums\ReparacionTipo;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductoHistorialIndex extends Component
{
    public $producto;

    public function mount($producto_id)
    {
        // findOrFail y no find(): con un id inexistente el blade reventaba mas
        // adelante al leer $producto->imei sobre null.
        $this->producto = Producto::findOrFail($producto_id);
    }

    public function render()
    {
        return view('livewire.producto-historial.index', [
            'resumenReparaciones' => $this->resumenReparaciones(),
        ]);
    }

    /**
     * Cifras de referencia de las reparaciones de este producto.
     *
     * Van aqui y no en la tabla a proposito: son FIJAS, no se mueven con los
     * filtros de la pestana. El pie de la tabla dice "esto es lo que estoy
     * mirando"; estas tarjetas dicen "esto es lo que el telefono lleva gastado".
     *
     * total_bs es la cifra de cabecera porque es puro apilado de importes
     * guardados en Bs. inventario_usd replica el criterio EXACTO de
     * Producto::recalcularCosto() -SUM(costo_total) de tipo != Externo- para
     * poder compararlo con lo que quedo escrito en productos.costo_reparacion.
     */
    protected function resumenReparaciones(): object
    {
        $externo = ReparacionTipo::Externo->value;

        return DB::table('productos_reparaciones')
            ->where('producto_id', $this->producto->id)
            ->selectRaw('COUNT(*) as reparaciones')
            ->selectRaw("COALESCE(SUM(estado = 'Pendiente'), 0) as pendientes")
            ->selectRaw('COALESCE(SUM(costo_total_bs), 0) as total_bs')
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo <> ? THEN costo_total ELSE 0 END), 0) as inventario_usd', [$externo])
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo =  ? THEN cobro_cliente ELSE 0 END), 0) as cobrado_externo_bs', [$externo])
            // Con tipo_cambio <= 0 el costo_total en USD de esa fila se guardo
            // en 0 (ver calcularTotalReparacion), y con tipo_cambio = 1 se
            // guardo el importe en Bs rotulado USD. Las dos cosas envenenan el
            // total en USD Y productos.costo_reparacion, asi que se cuentan para
            // poder avisarlo en vez de mostrar una cifra rota en silencio.
            ->selectRaw('COALESCE(SUM(tipo_cambio <= 1), 0) as tipo_cambio_sospechoso')
            ->first();
    }
}
