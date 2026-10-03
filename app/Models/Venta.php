<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use Auditable;

    protected $table = 'ventas';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $fillable = [
        'subtotal',
        'descuento',
        'total',
        'tipo_cambio',
        'total_bs',
        'cliente',
        'cliente_id',
        'user_id',
        'sucursal_id',
        'clave_idempotencia',
    ];

    /**
     * La ficha del cliente. Se llama fichaCliente y NO cliente a proposito:
     * `cliente` es una COLUMNA de esta tabla (el nombre congelado), y una
     * relacion con ese nombre quedaria tapada por el atributo -- Eloquent
     * resuelve primero los atributos, asi que $documento->cliente seguiria
     * devolviendo el string y la relacion seria inalcanzable.
     */
    public function fichaCliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * El nombre del cliente para mostrar. LA REGLA DE LECTURA, EN UN SOLO SITIO.
     *
     * La ficha primero y el texto como respaldo: asi corregir un nombre mal
     * escrito se ve en TODAS sus ventas, y las filas anteriores al modulo de
     * clientes -- que tienen texto pero no ficha -- siguen mostrando lo que
     * decian. El texto congelado solo se PINTA cuando no hay ficha.
     *
     * Existe como metodo y no como ternario repetido en cada blade porque son
     * siete sitios que pintan el cliente, y la regla tiene que ser una.
     */
    public function nombreCliente(): ?string
    {
        return $this->fichaCliente?->nombre ?? $this->cliente;
    }

    public function detalles()
    {
        return $this->hasMany(VentaProducto::class, 'venta_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Las ventas de repuestos cobradas junto a esta venta.
     *
     * hasMany y no hasOne: agregar productos a una venta ya registrada llama
     * otra vez al servicio, y eso crea una segunda cabecera para el mismo
     * venta_id.
     */
    public function ventasRepuestos()
    {
        return $this->hasMany(VentaRepuesto::class, 'venta_id');
    }

    /** Lo cobrado en repuestos, que va POR ENCIMA del total del telefono. */
    public function totalRepuestosCobrados(): float
    {
        return round((float) $this->ventasRepuestos->sum('total'), 2);
    }

    public function recalcularTotal()
    {
        $subtotal = $this->detalles()->sum('subtotal');
        $total = $subtotal - $this->descuento;
        $total_bs = $total * $this->tipo_cambio;
        $this->subtotal = $subtotal;
        $this->total = $total;
        $this->total_bs = $total_bs;
        $this->save();
    }
}
