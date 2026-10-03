<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * El stock de un repuesto en una sucursal. La UNICA verdad del stock.
 *
 * `repuestos.cantidad` es desde ahora un total cacheado, espejo de la suma de
 * estas filas. Si los dos discrepan, manda esta tabla y el total se arregla con
 * StockRepuestoService::recalcularTotales().
 *
 * NO se escribe desde los componentes. El unico camino de escritura es
 * StockRepuestoService: antes de existir, el stock se movia con
 * increment()/decrement() directos desde dieciseis sitios en ocho archivos, que
 * es la deuda que el CLAUDE.md del repo señala como principal.
 */
class RepuestoSucursal extends Model
{
    protected $table = 'repuestos_sucursales';
    protected $guarded = ['id'];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
