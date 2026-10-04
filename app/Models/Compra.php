<?php

namespace App\Models;

use App\Enums\CompraEstado;
use App\Enums\LineaTipo;
use App\Enums\ReclamoEstado;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Una compra a un proveedor: equipos, repuestos y accesorios en un solo
 * documento, en Bs. Lo comprado vive en compras_detalles, que escribe solo
 * CompraService; lo pagado al proveedor, en compras_pagos (PagoProveedorService);
 * los reclamos, en compras_reclamos (ReclamoService).
 *
 * `saldo` es GENERADA (total - pagado) y el estado se DERIVA de los reclamos.
 */
class Compra extends Model
{
    use Auditable;

    protected $table = 'compras';
    // `saldo` es generada: MySQL rechaza escribirla.
    protected $guarded = ['id', 'saldo'];

    protected $casts = [
        'fecha' => 'date',
        'pagada_at' => 'datetime',
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

    public function pagos()
    {
        return $this->hasMany(CompraPago::class, 'compra_id');
    }

    public function reclamos()
    {
        return $this->hasMany(CompraReclamo::class, 'compra_id');
    }

    public function saldoPendiente(): float
    {
        return round(max(0, (float) $this->total - (float) $this->pagado), 2);
    }

    public function scopeConSaldo(Builder $query): Builder
    {
        return $query->where('compras.saldo', '>', 0);
    }

    /**
     * Recibida / Con reclamo / Resuelta, derivado de los reclamos. Usa
     * reclamos_abiertos y reclamos_total si vienen del SELECT (withCount), y si
     * no, los cuenta.
     */
    public function estado(): CompraEstado
    {
        $abiertos = $this->reclamos_abiertos ?? $this->reclamos()->where('estado', ReclamoEstado::Abierto->value)->count();
        $total = $this->reclamos_total ?? $this->reclamos()->count();

        return match (true) {
            $abiertos > 0 => CompraEstado::ConReclamo,
            $total > 0 => CompraEstado::Resuelta,
            default => CompraEstado::Recibida,
        };
    }

    /** Los conteos que estado() necesita, en el mismo SELECT. */
    public function scopeConConteoReclamos(Builder $query): Builder
    {
        return $query->withCount([
            'reclamos as reclamos_total',
            'reclamos as reclamos_abiertos' => fn($q) => $q->where('estado', ReclamoEstado::Abierto->value),
        ]);
    }

    public function scopeConEstado(Builder $query, CompraEstado $estado): Builder
    {
        $abierto = fn($q) => $q->where('estado', ReclamoEstado::Abierto->value);

        return match ($estado) {
            CompraEstado::ConReclamo => $query->whereHas('reclamos', $abierto),
            CompraEstado::Resuelta => $query->whereHas('reclamos')->whereDoesntHave('reclamos', $abierto),
            CompraEstado::Recibida => $query->whereDoesntHave('reclamos'),
        };
    }

    /**
     * total = SUM(lineas.subtotal), desde la base. No puede quedar por debajo de
     * lo ya pagado al proveedor (CHECK compras_pagado_rango): se avisa antes.
     */
    public function recalcularTotal(): void
    {
        $total = round((float) $this->detalles()->sum('subtotal'), 2);

        if ($total < (float) $this->pagado) {
            throw ValidationException::withMessages([
                'detalles' => 'La compra #' . $this->id . ' ya tiene Bs ' . number_format((float) $this->pagado, 2)
                    . ' pagados al proveedor y el total quedaría en Bs ' . number_format($total, 2) . '. Anula un pago antes.',
            ]);
        }

        $this->total = $total;
        $this->save();

        // Con otro total puede quedar pagada o volver a tener saldo: pagada_at
        // lo decide PagoProveedorService.
        app(\App\Services\PagoProveedorService::class)->sincronizar($this);
    }
}
