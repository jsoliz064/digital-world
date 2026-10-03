<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una transferencia de stock entre sucursales.
 *
 * Es el documento que respalda el unico movimiento de stock que no nace de una
 * compra, una venta ni una reparacion. Sin el, la transferencia seria invisible
 * en el historial del articulo y el banner de "el balance calculado no coincide"
 * se disparararia por diseño en cada articulo transferido.
 *
 * RepuestoMovimiento lee cada fila como DOS movimientos: una Salida en el origen
 * y una Entrada en el destino. Es lo que mantiene el saldo del historial neto
 * cero y cuadrado con el total.
 */
class RepuestoTransferencia extends Model
{
    protected $table = 'repuestos_transferencias';
    protected $guarded = ['id'];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }

    public function origen()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function destino()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
