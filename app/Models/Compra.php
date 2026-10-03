<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $table = 'compras';
    protected $guarded = ['id'];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function productos()
    {
        return $this->hasMany(Producto::class, 'compra_id');
    }

    public function recalculate()
    {
        $this->costo_total = $this->productos()->sum('costo_total');
        $this->cantidad_total = $this->productos()->count();
        $this->save();
    }
}
