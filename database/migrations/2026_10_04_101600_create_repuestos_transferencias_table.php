<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los nombres de las FK van explicitos: MySQL corta a 64 caracteres.
        Schema::create('repuestos_transferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repuesto_id')->constrained('repuestos')->cascadeOnDelete();
            $table->unsignedBigInteger('sucursal_origen_id');
            $table->unsignedBigInteger('sucursal_destino_id');
            $table->integer('cantidad');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('sucursal_origen_id', 'rt_origen_foreign')
                ->references('id')->on('sucursales')->restrictOnDelete();
            $table->foreign('sucursal_destino_id', 'rt_destino_foreign')
                ->references('id')->on('sucursales')->restrictOnDelete();
            $table->index(['repuesto_id', 'created_at'], 'rt_repuesto_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repuestos_transferencias');
    }
};
