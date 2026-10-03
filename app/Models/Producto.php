<?php

namespace App\Models;

use App\Enums\ProductoEstado;
use App\Enums\ReparacionTipo;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use Auditable;

    protected $table = 'productos';
    protected $guarded = ['id'];

    /**
     * Busca por IMEI ordenando por relevancia.
     *
     * Los clientes suelen escribir solo los últimos dígitos del IMEI, así que las
     * coincidencias por sufijo van primero. Se sigue devolviendo cualquier
     * coincidencia parcial, pero al final de la lista.
     *
     * Orden: exacto → termina en → empieza con → contiene.
     */
    public function scopeBuscarPorImei(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        // Neutraliza los comodines de LIKE para que un '%' o '_' escrito por el
        // usuario se busque literalmente.
        $patron = addcslashes($termino, '%_\\');

        return $query
            ->where('imei', 'like', '%' . $patron . '%')
            ->orderByRaw(
                'CASE
                    WHEN imei = ? THEN 0
                    WHEN imei LIKE ? THEN 1
                    WHEN imei LIKE ? THEN 2
                    ELSE 3
                END',
                [$termino, '%' . $patron, $patron . '%']
            )
            ->orderBy('imei');
    }

    /** Equipos que se pueden vender. Ver ProductoEstado::disponibles(). */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->whereIn('estado', ProductoEstado::disponibles());
    }

    /** La misma pregunta, sobre una fila ya cargada. */
    public function estaDisponible(): bool
    {
        return in_array($this->estado, ProductoEstado::disponibles(), true);
    }

    public function imagenes()
    {
        return $this->hasMany(ProductoImagen::class);
    }

    public function reparaciones()
    {
        return $this->hasMany(ProductoReparacion::class, 'producto_id');
    }

    public function modelo()
    {
        return $this->belongsTo(ProductoModelo::class, 'producto_modelo_id');
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function ventaProducto()
    {
        return $this->hasOne(VentaProducto::class, 'producto_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function ultimaReparacion()
    {
        return ProductoReparacion::where('producto_id', $this->id)->orderBy('id', 'desc')->first();
    }

    public function ultimaReparacionPendiente()
    {
        return ProductoReparacion::where('producto_id', $this->id)->where('estado', 'Pendiente')->orderBy('id', 'desc')->first();
    }

    public function recalcularCosto()
    {
        $costo_total = $this->costo_unidad + $this->costo_envio;

        // El trabajo externo lo paga el cliente: no es costo de inventario.
        // Sumarlo reescribiria el costo de un telefono ya vendido y, como el
        // producto conserva su compra_id, Compra::recalculate() lo arrastraria
        // al total del lote, que los reportes leen como "Inversion".
        //
        // La exclusion va aqui y no en quien llama: si solo se omitiera la
        // llamada desde el modal, la siguiente edicion del tecnico desde
        // ReparacionEditModal volveria a sumarlo.
        $costo_reparacion = $this->reparaciones()
            ->where('tipo', '!=', ReparacionTipo::Externo->value)
            ->sum('costo_total');

        $this->costo_reparacion = $costo_reparacion;
        $this->costo_total = $costo_total + $costo_reparacion;
        $this->save();
    }
}
