<?php

namespace App\Models;

use App\Enums\LineaTipo;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Una venta: equipos, repuestos y accesorios en un solo documento, en Bs. Lo
 * vendido vive en ventas_detalles, que escribe solo VentaService.
 */
class Venta extends Model
{
    use Auditable;

    protected $table = 'ventas';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * OJO: con $guarded y $fillable a la vez gana $fillable, y una columna que
     * falte aqui se descarta en el create() SIN ERROR. Ya paso con mano_obra, y
     * sin clave_idempotencia toda la idempotencia quedaria inerte.
     */
    protected $fillable = [
        'subtotal',
        'descuento',
        'mano_obra',
        'total',
        'costo_total',
        'cliente',
        'cliente_id',
        'user_id',
        'sucursal_id',
        'clave_idempotencia',
    ];

    /**
     * La ficha del cliente. Se llama fichaCliente y NO cliente a proposito:
     * `cliente` es una COLUMNA de esta tabla (el nombre congelado), y una
     * relacion con ese nombre quedaria tapada por el atributo.
     */
    public function fichaCliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * El nombre del cliente para mostrar. LA REGLA DE LECTURA, EN UN SOLO SITIO:
     * la ficha primero y el texto congelado como respaldo.
     */
    public function nombreCliente(): ?string
    {
        return $this->fichaCliente?->nombre ?? $this->cliente;
    }

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    /** Las lineas de equipo. */
    public function detallesProductos()
    {
        return $this->detalles()->where('tipo', LineaTipo::Producto->value);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function ganancia(): float
    {
        return round((float) $this->total - (float) $this->costo_total, 2);
    }

    /**
     * Recalcula los totales DESDE LA BASE, nunca desde el formulario:
     *   subtotal    = SUM(lineas.subtotal)
     *   total       = subtotal - descuento + mano_obra
     *   costo_total = SUM(lineas.subtotal_costo) + mano_obra
     * mano_obra suma al total Y al costo para que se cancele en la ganancia.
     */
    public function recalcularTotales(): void
    {
        $sumas = $this->detalles()
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal, COALESCE(SUM(subtotal_costo), 0) as costo')
            ->first();

        $manoObra = (float) $this->mano_obra;

        $this->subtotal = round((float) $sumas->subtotal, 2);
        $this->total = round($this->subtotal - (float) $this->descuento + $manoObra, 2);
        $this->costo_total = round((float) $sumas->costo + $manoObra, 2);
        $this->save();
    }
}
