<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * La ficha de un cliente.
 *
 * Sustituye al nombre escrito a mano que vivia en `ventas.cliente` y
 * `ventas_repuestos.cliente`. Esas dos columnas siguen ahi como archivo de lo
 * que se escribio en cada operacion; quien manda para mostrar y para navegar es
 * esta ficha (ver Venta::nombreCliente()).
 *
 * Solo $guarded y NUNCA $fillable: declarar los dos es la trampa que el
 * CLAUDE.md documenta -- gana $fillable, y una columna que falte ahi la descarta
 * create() en silencio. Ya se cobro a mano_obra y a clave_idempotencia.
 */
class Cliente extends Model
{
    use Auditable;

    protected $table = 'clientes';
    protected $guarded = ['id'];

    /** Las ventas de telefonos de este cliente. */
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    /** Sus ventas de repuestos y accesorios, incluidas las enlazadas a una venta. */
    public function ventasRepuestos()
    {
        return $this->hasMany(VentaRepuesto::class, 'cliente_id');
    }

    /**
     * Cuantas ordenes tiene, para el modal de eliminar.
     *
     * Las ventas de repuestos ENLAZADAS no se cuentan: no son una operacion
     * aparte, son la misma venta al mismo cliente, ya contada en las de
     * telefonos. Es el criterio que ReporteIndex ya fijo para el ticket promedio.
     */
    public function cantidadOrdenes(): int
    {
        return $this->ventas()->count()
            + $this->ventasRepuestos()->whereNull('venta_id')->count();
    }
}
