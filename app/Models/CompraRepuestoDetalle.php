<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraRepuestoDetalle extends Model
{
    protected $table = 'compras_repuestos_detalles';
    protected $guarded = ['id'];

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }
}
