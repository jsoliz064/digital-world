<?php

namespace App\Models;

use App\Enums\LineaTipo;
use Illuminate\Database\Eloquent\Model;

/**
 * Una linea de venta: un equipo, un repuesto o un accesorio. La escribe solo
 * VentaService (y RepuestosDeReparacionService para los cobros de taller).
 *
 * `tipo` y `articulo_clave` son columnas GENERADAS por MySQL: NO van en
 * $fillable (escribirlas da el error 3105). Y como el modelo declara $fillable,
 * una columna nueva que falte aqui se descarta EN SILENCIO en el create().
 */
class VentaDetalle extends Model
{
    protected $table = 'ventas_detalles';

    protected $fillable = [
        'venta_id',
        'producto_id',
        'repuesto_id',
        'accesorio_id',
        'producto_reparacion_repuesto_id',
        'sucursal_id',
        'cantidad',
        'costo',
        'precio',
        'descuento',
        'subtotal',
        'subtotal_costo',
        'garantia_meses',
        'garantia_fecha_exp',
        'tipo_venta',
        'producto_asociado_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'garantia_fecha_exp' => 'date',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /** El equipo con el que se vendio este accesorio o repuesto, si lo hay. */
    public function productoAsociado()
    {
        return $this->belongsTo(Producto::class, 'producto_asociado_id');
    }

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }

    public function accesorio()
    {
        return $this->belongsTo(Accesorio::class, 'accesorio_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /** La pieza de reparacion que cobra esta linea, si es un cobro de taller. */
    public function reparacionRepuesto()
    {
        return $this->belongsTo(ProductoReparacionRepuesto::class, 'producto_reparacion_repuesto_id');
    }

    public function tipoLinea(): LineaTipo
    {
        return LineaTipo::from($this->tipo);
    }

    /** El modelo vendido, sea cual sea su tipo. */
    public function articulo(): ?Model
    {
        return $this->producto ?? $this->repuesto ?? $this->accesorio;
    }

    public function esProducto(): bool
    {
        return $this->producto_id !== null;
    }

    /**
     * El stock de esta linea ya salio cuando el tecnico monto la pieza, asi que
     * NADIE debe moverlo al crear, editar o anular la venta.
     */
    public function stockYaDescontado(): bool
    {
        return $this->producto_reparacion_repuesto_id !== null;
    }

    /** Lo que se muestra de la linea: modelo + IMEI del equipo, o nombre del articulo. */
    public function descripcion(): string
    {
        if ($this->producto_id) {
            $p = $this->producto;

            return trim(($p?->modelo?->nombre ?? 'Equipo') . ' ' . ($p?->almacenamiento ?? '')) . ' · IMEI ' . ($p?->imei ?? '');
        }

        return $this->articulo()?->nombre ?? 'Artículo';
    }
}
