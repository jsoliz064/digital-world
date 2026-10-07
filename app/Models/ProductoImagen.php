<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoImagen extends Model
{
    protected $table = 'productos_imagenes';
    protected $guarded = ['id'];

    protected $fillable = ['base64', 'producto_id'];

    /** Su miniatura de las tablas (ProductoMiniaturaController) se va con ella. */
    protected static function booted(): void
    {
        static::deleted(function (ProductoImagen $imagen) {
            @unlink(\App\Http\Controllers\ProductoMiniaturaController::ruta($imagen->id));
        });
    }
}
