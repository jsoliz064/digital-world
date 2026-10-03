<?php

namespace App\Models;

use App\Enums\LineaTipo;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Una compra a un proveedor: equipos, repuestos y accesorios en un solo
 * documento, en Bs. Lo comprado vive en compras_detalles, que escribe solo
 * CompraService.
 */
class Compra extends Model
{
    use Auditable;

    protected $table = 'compras';
    protected $guarded = ['id'];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    /** Los equipos que entraron por esta compra, a traves de sus lineas. */
    public function productos()
    {
        return $this->hasManyThrough(Producto::class, CompraDetalle::class, 'compra_id', 'id', 'id', 'producto_id');
    }

    /** Las lineas de repuestos y accesorios. */
    public function detallesArticulos()
    {
        return $this->detalles()->where('tipo', '!=', LineaTipo::Producto->value);
    }

    /** total = SUM(lineas.subtotal), desde la base. */
    public function recalcularTotal(): void
    {
        $this->total = round((float) $this->detalles()->sum('subtotal'), 2);
        $this->save();
    }
}
