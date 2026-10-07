<?php

namespace App\Http\Controllers;

use App\Models\ProductoImagen;

/**
 * La miniatura de una foto de equipo, para las tablas (Productos y el detalle
 * de la compra).
 *
 * Por URL y no el base64 en linea: cada foto pesa 100-300 KB, y rappasoft
 * redibuja la tabla en cada filtro, busqueda o pagina; 25 filas eran varios MB
 * por render. Se reduce una vez a 96 px con GD y queda en disco.
 *
 * Cache eterno: una imagen no se edita nunca (editar fotos borra filas y crea
 * otras), asi que su id identifica siempre los mismos bytes.
 */
class ProductoMiniaturaController extends Controller
{
    public const LADO = 96;

    public static function ruta(int $imagenId): string
    {
        return storage_path("app/miniaturas/{$imagenId}.jpg");
    }

    /**
     * La celda «Foto» de las tablas: la miniatura, o un icono si el equipo no
     * tiene fotos. Una sola copia para Productos y la compra.
     */
    public static function html(?int $imagenId): string
    {
        if (!$imagenId) {
            return '<span class="flex h-10 w-10 items-center justify-center rounded-md bg-gray-100 text-gray-400 dark:bg-gray-700"><i class="fa-solid fa-mobile-screen"></i></span>';
        }

        return '<img src="' . e(route('productos.miniatura', $imagenId)) . '" loading="lazy" alt="" class="h-10 w-10 rounded-md object-cover">';
    }

    /** El id de la primera foto de cada equipo, como subconsulta del builder (sin N+1). */
    public static function subconsultaPrimera(): \Illuminate\Database\Eloquent\Builder
    {
        return ProductoImagen::selectRaw('MIN(id)')->whereColumn('producto_id', 'productos.id');
    }

    public function __invoke(int $imagen)
    {
        $ruta = self::ruta($imagen);

        if (!is_file($ruta)) {
            $base64 = ProductoImagen::whereKey($imagen)->value('base64');
            abort_if($base64 === null, 404);

            $this->generar($base64, $ruta);
        }

        // setPrivate(): BinaryFileResponse se marca public sola, y va detras del login.
        return response()->file($ruta, ['Content-Type' => 'image/jpeg'])
            ->setPrivate()
            ->setMaxAge(31536000)
            ->setImmutable();
    }

    /** Recorta al centro en cuadrado y la reduce a LADO x LADO. */
    private function generar(string $base64, string $ruta): void
    {
        $datos = base64_decode(preg_replace('/^data:image\/[a-z0-9.+-]+;base64,/i', '', $base64), true);
        $origen = $datos !== false ? @imagecreatefromstring($datos) : false;
        abort_if($origen === false, 404);

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $lado = min($ancho, $alto);

        $mini = imagecreatetruecolor(self::LADO, self::LADO);
        imagecopyresampled($mini, $origen, 0, 0, intdiv($ancho - $lado, 2), intdiv($alto - $lado, 2), self::LADO, self::LADO, $lado, $lado);

        if (!is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }

        imagejpeg($mini, $ruta, 80);
        imagedestroy($origen);
        imagedestroy($mini);
    }
}
