<?php

namespace App\Models;

use App\Enums\PagoMomento;
use Illuminate\Database\Eloquent\Model;

/**
 * Un pago de una venta: al venderla o en un cobro posterior. Lo escribe y lo
 * anula SOLO PagoService, que mantiene ventas.pagado.
 *
 * Sin Auditable: un pago es un hecho que no se edita. Su alta y su anulacion
 * quedan en la bitacora de la VENTA (eventos pago / pago-anulado), junto al
 * cambio de `pagado` que provocan.
 */
class VentaPago extends Model
{
    protected $table = 'ventas_pagos';

    protected $fillable = [
        'venta_id',
        'metodo_pago_id',
        'monto',
        'momento',
        'fecha',
        'nota',
        'user_id',
        'clave_idempotencia',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'momento' => PagoMomento::class,
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function metodo()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
