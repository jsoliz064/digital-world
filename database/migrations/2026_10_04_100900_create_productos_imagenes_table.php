<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_imagenes', function (Blueprint $table) {
            $table->id();
            $table->longText('base64');
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_imagenes');
    }
};
