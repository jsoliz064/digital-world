<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class VentaRepuesto extends Model
{
    use Auditable;

    protected $table = 'ventas_repuestos';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $fillable = [
        'cantidad_repuestos',
        'subtotal',
        'descuento',
        'mano_obra',
        'costo_total',
        'total',
        'tipo_cambio',
        'total_bs',
        // Lo cobrado de mas (o de menos) sobre la conversion: total_bs deja de
        // ser total * tipo_cambio y pasa a ser base + este ajuste.
        'ajuste_bs',
        'costo_total_bs',
        'cliente',
        // Sin esta linea la ficha no entra y la venta queda sin enlazar.
        'cliente_id',
        'user_id',
        'sucursal_id',
        // Gana $fillable sobre $guarded: sin esta linea el create() la
        // descartaria en silencio, como ya paso con mano_obra.
        'venta_id',
        // Y por lo mismo esta: sin la linea, la clave llega como NULL, el indice
        // unico admite todos los NULL que quieras y la idempotencia queda
        // inerte -- sin un solo error que lo delate.
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
        return $this->hasMany(VentaRepuestoDetalle::class, 'venta_repuesto_id');
    }

    /** La venta del telefono que origino esta venta de repuestos, si la hubo. */
    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    /** Venta de repuestos cobrada junto a un telefono. */
    public function esEnlazada(): bool
    {
        return $this->venta_id !== null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
