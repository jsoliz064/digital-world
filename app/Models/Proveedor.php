<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';
    protected $guarded = ['id'];

    public function compra()
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }
}
