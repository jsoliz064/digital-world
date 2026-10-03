<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * La ficha de un cliente.
 *
 * Sustituye al nombre escrito a mano en `ventas.cliente`. Esa columna sigue ahi
 * como archivo de lo que se escribio en cada venta; quien manda para mostrar y
 * para navegar es esta ficha (ver Venta::nombreCliente()).
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

    /** Sus ventas: equipos, repuestos y accesorios van en el mismo documento. */
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    /** Cuantas ordenes tiene, para el modal de eliminar. */
    public function cantidadOrdenes(): int
    {
        return $this->ventas()->count();
    }
}
