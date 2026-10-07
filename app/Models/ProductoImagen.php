<?php

namespace App\Models;

use App\Http\Controllers\ProductoMiniaturaController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Una foto del equipo: un archivo en el disco `public`
 * (productos/{producto_id}/{uuid}.jpg) y su ruta aqui. Antes era el data URL
 * base64 en la base, que inflaba los respaldos y viajaba en cada peticion de
 * Livewire. Se guarda SOLO con guardar() y se borra por Eloquent: el evento
 * deleted se lleva el archivo y su miniatura.
 */
class ProductoImagen extends Model
{
    public const DISCO = 'public';
    public const LADO_MAXIMO = 1280;

    protected $table = 'productos_imagenes';
    protected $guarded = ['id'];

    protected $fillable = ['ruta', 'producto_id'];

    protected static function booted(): void
    {
        static::deleted(function (ProductoImagen $imagen) {
            Storage::disk(self::DISCO)->delete($imagen->ruta);
            @unlink(ProductoMiniaturaController::ruta($imagen->id));
        });
    }

    /**
     * Guarda la foto como JPEG de 1280 px de lado mayor como mucho: la de la
     * camara ya viene asi, y una de galeria pesaria diez veces mas. Primero el
     * archivo y despues la fila: si la transaccion falla queda un archivo
     * huerfano (inocuo), nunca una fila que apunte a la nada.
     */
    public static function guardar(Producto $producto, UploadedFile $archivo): self
    {
        $origen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        if ($origen === false) {
            throw ValidationException::withMessages(['fotos' => 'La foto no es una imagen válida.']);
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $escala = min(1, self::LADO_MAXIMO / max($ancho, $alto));
        $destino = $origen;

        if ($escala < 1) {
            $destino = imagecreatetruecolor((int) round($ancho * $escala), (int) round($alto * $escala));
            imagecopyresampled($destino, $origen, 0, 0, 0, 0, imagesx($destino), imagesy($destino), $ancho, $alto);
        }

        ob_start();
        imagejpeg($destino, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($origen);
        if ($destino !== $origen) {
            imagedestroy($destino);
        }

        $ruta = "productos/{$producto->id}/" . Str::uuid() . '.jpg';
        Storage::disk(self::DISCO)->put($ruta, $jpeg);

        return self::create(['producto_id' => $producto->id, 'ruta' => $ruta]);
    }

    public function url(): string
    {
        return Storage::disk(self::DISCO)->url($this->ruta);
    }

    public function contenido(): ?string
    {
        return Storage::disk(self::DISCO)->get($this->ruta);
    }
}
