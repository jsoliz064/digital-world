<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las fotos del equipo pasan de un data URL base64 en la base a archivos en
     * el disco `public`: el base64 inflaba la base y los respaldos, viajaba en
     * cada peticion de Livewire y el navegador no podia cachearlo. Produccion
     * aun no tenia fotos (decision del usuario), asi que no se migran: se
     * descartan las de prueba.
     */
    public function up(): void
    {
        DB::table('productos_imagenes')->delete();

        Schema::table('productos_imagenes', function (Blueprint $table) {
            $table->dropColumn('base64');
        });

        Schema::table('productos_imagenes', function (Blueprint $table) {
            // Relativa al disco public: productos/{producto_id}/{uuid}.jpg
            $table->string('ruta')->after('id');
        });

        // Las miniaturas de las fotos borradas (el delete de arriba no pasa por
        // Eloquent, asi que su evento no las limpio) quedarian huerfanas.
        if (File::isDirectory(storage_path('app/miniaturas'))) {
            File::cleanDirectory(storage_path('app/miniaturas'));
        }
    }

    public function down(): void
    {
        DB::table('productos_imagenes')->delete();

        Schema::table('productos_imagenes', function (Blueprint $table) {
            $table->dropColumn('ruta');
        });

        Schema::table('productos_imagenes', function (Blueprint $table) {
            $table->longText('base64')->nullable()->after('id');
        });
    }
};
