<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tecnicos extends Model
{
    protected $table = 'tecnicos';
    protected $guarded = ['id'];

    public function reparaciones()
    {
        return $this->hasMany(ProductoReparacion::class, 'tecnico_id');
    }

    public function getDivColor()
    {
        $color = $this->color;
        $style = $color ? "background-color: {$color};" : "background-color: transparent;";
        return '<div class="w-6 h-6 rounded-full border border-gray-300 mx-auto" style="' . $style . '"></div>';
    }

    public function getPColor()
    {
        $color = $this->color;
        $style = $color ? "color: {$color};" : "color: transparent;";
        return '<p class="inline-block ml-2" style="' . $style . '">' . $this->nombre . '</p>';
    }
}
