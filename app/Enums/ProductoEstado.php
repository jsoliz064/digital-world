<?php

namespace App\Enums;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

enum ProductoEstado: string
{
    case Inventario = 'Inventario';
    case Oferta = 'Oferta';
    case Reparacion = 'Reparacion';
    case Fuera = 'Fuera';
    case Vendido = 'Vendido';
    case Roto = 'Roto';
    case Transito = 'Transito';

    public function label(): string
    {
        return match ($this) {
            self::Inventario => 'Inventario',
            self::Oferta => 'Oferta',
            self::Reparacion => 'Reparacion',
            self::Fuera => 'Fuera',
            self::Vendido => 'Vendido',
            self::Roto => 'Roto',
            self::Transito => 'Transito',
        };
    }

    /**
     * Los estados en que un equipo esta disponible para vender.
     *
     * Oferta es Inventario con un cartel: misma logica en todo el sistema, solo
     * cambia como se ve. Existe como lista y no como comparacion suelta porque
     * la pregunta "se puede vender?" se hacia en quince sitios con un
     * `=== Inventario` a secas, y agregar este estado obligaba a acertar en los
     * quince.
     *
     * @return string[]
     */
    public static function disponibles(): array
    {
        return [self::Inventario->value, self::Oferta->value];
    }

    /**
     * El color con que se pinta el estado. Va a un `style` inline, nunca a una
     * clase de Tailwind: son nombres de color de CSS (el proyecto no tiene
     * safelist, asi que 'bg-' . $color no se generaria).
     *
     * Vivia copiado en las cuatro tablas que muestran el estado, y ninguna
     * copia era igual a las otras: dos no conocian Transito y el tablero
     * pintaba Vendido de azul. Al agregar Oferta se pinto en una sola y las
     * otras tres la dejaron verde, indistinguible de Inventario.
     */
    public function color(): string
    {
        return match ($this) {
            self::Inventario => 'green',
            // Se vende igual que Inventario, pero se busca con la vista.
            self::Oferta => 'purple',
            self::Reparacion => 'red',
            self::Fuera => 'orange',
            self::Vendido => 'yellow',
            self::Roto => 'blue',
            self::Transito => 'gray',
        };
    }

    /**
     * Lo mismo para el valor crudo de la columna, que es lo que reciben los
     * `format()` de las tablas. Un estado que no conozca cae en verde, que es
     * el valor por omision que tenian las cuatro copias.
     */
    public static function colorDe(?string $estado): string
    {
        return self::tryFrom((string) $estado)?->color() ?? 'green';
    }

    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]);
    }

    public static function toSelectArrayPermission(): Collection
    {
        return collect(self::cases())
            ->filter(function ($case) {
                // Construye el nombre del permiso dinámicamente. Ej: 'producto.estado.inventario'
                $permissionName = 'producto.estado.' . strtolower($case->value);

                // Retorna true solo si el usuario está logueado y tiene el permiso
                return Auth::user() && Auth::user()->can($permissionName);
            })
            ->mapWithKeys(fn($case) => [$case->value => $case->label()]);
    }

    public static function validatePermission($estado)
    {
        $permissionName = 'producto.estado.' . strtolower($estado);
        return Auth::user() && Auth::user()->can($permissionName);
    }
}
