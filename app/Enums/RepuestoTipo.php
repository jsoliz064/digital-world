<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Discrimina un articulo del catalogo entre pieza de reparacion y accesorio.
 *
 * Respalda la columna `repuestos.tipo` y, congelada, las columnas `tipo` de
 * ventas_repuestos_detalles y compras_repuestos_detalles: ahi guarda el tipo
 * que el articulo tenia EN EL MOMENTO de la operacion, para que reclasificar
 * un articulo no reescriba los reportes de un periodo ya cerrado.
 */
enum RepuestoTipo: string
{
    case Repuesto = 'Repuesto';
    case Accesorio = 'Accesorio';

    public function label(): string
    {
        return match ($this) {
            self::Repuesto => 'Repuesto',
            self::Accesorio => 'Accesorio',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Repuesto => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Accesorio => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        };
    }

    /**
     * Si este tipo tiene los atributos de una pieza de reparacion.
     *
     * Fabricante, modelo, categoria y color describen el telefono al que encaja
     * la pieza: un accesorio no es pieza de nada y los guarda en NULL. De aqui
     * salen los filtros, las columnas y las fichas que se esconden en la
     * pantalla de accesorios.
     *
     * REPARTO DE RESPONSABILIDADES: este metodo contesta SI, y
     * RepuestoAccesorioTrait::camposSoloRepuesto() sigue siendo el dueño de
     * CUALES. Su docblock promete que agregar un atributo de pieza se hace en un
     * solo sitio; si las pantallas listaran los campos por su cuenta, esa
     * promesa se rompia en cinco archivos.
     */
    public function tieneCamposDeRepuesto(): bool
    {
        return $this === self::Repuesto;
    }

    /**
     * El prefijo de permiso de cada tipo: repuesto.* o accesorio.*.
     *
     * Existe para que el literal se escriba UNA vez. De aqui salen los @can de
     * crear, editar y eliminar, y el middleware can: de las dos rutas; con un
     * literal por sitio, el octavo se escribe mal.
     *
     * OJO: repuesto.historial, repuesto.tipo-cambio-masivo y repuesto.transferir
     * NO se derivan de aqui a proposito: son una ruta y dos operaciones
     * compartidas por las dos pantallas, sobre la misma tabla.
     */
    public function permiso(): string
    {
        return match ($this) {
            self::Repuesto => 'repuesto',
            self::Accesorio => 'accesorio',
        };
    }

    /** El nombre de la ruta del listado de cada tipo. */
    public function ruta(): string
    {
        return match ($this) {
            self::Repuesto => 'repuestos',
            self::Accesorio => 'accesorios',
        };
    }

    /** El titulo de la pantalla, en plural. */
    public function plural(): string
    {
        return match ($this) {
            self::Repuesto => 'Repuestos',
            self::Accesorio => 'Accesorios',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return Collection<string,string> value => label (opciones de select) */
    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]);
    }
}
