<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoImagen extends Model
{
    protected $table = 'productos_imagenes';
    protected $guarded = ['id'];

    protected $fillable = ['base64', 'producto_id'];
}
