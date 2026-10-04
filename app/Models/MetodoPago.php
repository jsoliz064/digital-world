<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un metodo de pago (Efectivo, QR...). Uno con pagos no se borra: se
 * desactiva, y deja de ofrecerse al cobrar. Los filtros siguen con todos.
 */
class MetodoPago extends Model
{
    protected $table = 'metodos_pago';
    protected $guarded = ['id'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function pagos()
    {
        return $this->hasMany(VentaPago::class, 'metodo_pago_id');
    }

    /** Los que se ofrecen al cobrar. */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }
}
