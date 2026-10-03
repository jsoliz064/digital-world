<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoReparacion extends Model
{
    protected $table = 'productos_reparaciones';
    protected $guarded = ['id'];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Tecnicos::class, 'tecnico_id');
    }

    public function repuestos()
    {
        return $this->hasMany(ProductoReparacionRepuesto::class, 'producto_reparacion_id');
    }
}
