<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaRepuestoDetalle extends Model
{
    protected $table = 'ventas_repuestos_detalles';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $fillable = [
        'costo',
        'precio',
        'cantidad',
        'descuento',
        'subtotal_costo',
        'subtotal',
        'tipo_cambio',
        'subtotal_costo_bs',
        'subtotal_bs',
        'repuesto_id',
        // El modelo declara $guarded Y $fillable a la vez: en Eloquent gana
        // $fillable, asi que una columna que falte aqui se descarta en el
        // create() SIN ERROR. Es lo que ya paso con mano_obra.
        'tipo',
        'venta_repuesto_id',
        'sucursal_id',
        'producto_reparacion_repuesto_id',
    ];

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }

    /** La linea de reparacion de la que sale esta venta, si viene de una. */
    public function reparacionRepuesto()
    {
        return $this->belongsTo(ProductoReparacionRepuesto::class, 'producto_reparacion_repuesto_id');
    }

    /**
     * El stock de esta linea ya salio del almacen cuando el tecnico monto la
     * pieza, asi que NADIE debe moverlo al crear, editar o borrar la venta.
     */
    public function stockYaDescontado(): bool
    {
        return $this->producto_reparacion_repuesto_id !== null;
    }
}
