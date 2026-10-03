<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class CompraRepuesto extends Model
{
    use Auditable;

    protected $table = 'compras_repuestos';
    protected $guarded = ['id'];

    public function detalles()
    {
        return $this->hasMany(CompraRepuestoDetalle::class, 'compra_repuesto_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * La sucursal a la que entro la mercaderia. Es el destino del stock de todas
     * las lineas, y no se cambia editando: las unidades ya entraron ahi.
     */
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
