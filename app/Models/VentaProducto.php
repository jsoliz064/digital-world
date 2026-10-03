<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaProducto extends Model
{
    protected $table = 'ventas_productos';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $fillable = [
        'costo',
        'precio',
        'descuento',
        'subtotal',
        'tipo_cambio',
        'subtotal_bs',
        'garantia_meses',
        'garantia_fecha_exp',
        'producto_id',
        'venta_id',
        'sucursal_id',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
