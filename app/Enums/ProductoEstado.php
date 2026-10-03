<?php

namespace App\Enums;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * El estado de un equipo. Alimenta el DDL de `productos.estado` (la migracion
 * lee este enum): agregar o quitar un caso exige migrate:fresh.
 *
 * OJO: Fuera significa que el equipo salio del local (lo tiene alguien, esta en
 * otro punto) y Roto que esta roto. NINGUNO de los dos es una baja: la baja es
 * un atributo aparte (`productos.dado_de_baja_at`, ver BajaService) que archiva
 * el equipo sin tocar su estado.
 *
 * Tampoco hay ya un estado Oferta: la oferta es `productos.tipo_venta`
 * (ProductoTipoVenta). Un equipo en oferta esta en Inventario, como cualquiera.
 */
enum ProductoEstado: string
{
    case Inventario = 'Inventario';
    case Reparacion = 'Reparacion';
    case Fuera = 'Fuera';
    case Roto = 'Roto';
    case Reserva = 'Reserva';
    case Credito = 'Credito';
    case Vendido = 'Vendido';

    public function label(): string
    {
        return match ($this) {
            self::Inventario => 'Inventario',
            self::Reparacion => 'Reparación',
            self::Fuera => 'Fuera',
            self::Roto => 'Roto',
            self::Reserva => 'Reserva',
            self::Credito => 'Venta a crédito',
            self::Vendido => 'Vendido',
        };
    }

    /**
     * Los estados en que un equipo esta disponible para vender.
     *
     * Existe como lista y no como comparacion suelta porque la pregunta "se
     * puede vender?" se hacia en quince sitios con un `=== Inventario` a secas.
     * Ojo: ademas del estado, un equipo dado de baja nunca esta disponible (ver
     * Producto::scopeDisponibles()).
     *
     * @return string[]
     */
    public static function disponibles(): array
    {
        return [self::Inventario->value];
    }

    /**
     * Los estados que exigen una linea de venta: el equipo salio vendido,
     * cobrado del todo (Vendido) o con saldo pendiente (Credito).
     *
     * @return string[]
     */
    public static function vendidos(): array
    {
        return [self::Vendido->value, self::Credito->value];
    }

    /**
     * Los que solo escribe una venta, nunca el selector de estado: elegirlos a
     * mano dejaria un equipo "vendido" sin venta, que es lo primero que caza el
     * auditor.
     *
     * @return string[]
     */
    public static function soloPorDocumento(): array
    {
        return self::vendidos();
    }

    /**
     * Los que no se muestran en el catalogo publico aunque esten marcados como
     * disponibles en catalogo: vendidos, rotos y reservados (docs/03: el
     * equipo reservado "no aparece para vender ni en el catalogo publico").
     *
     * @return string[]
     */
    public static function fueraDeCatalogo(): array
    {
        return [self::Vendido->value, self::Credito->value, self::Roto->value, self::Reserva->value];
    }

    /**
     * El color con que se pinta el estado. Va a un `style` inline, nunca a una
     * clase de Tailwind: son nombres de color de CSS (el proyecto no tiene
     * safelist, asi que 'bg-' . $color no se generaria).
     */
    public function color(): string
    {
        return match ($this) {
            self::Inventario => 'green',
            self::Reparacion => 'red',
            self::Fuera => 'orange',
            self::Roto => 'blue',
            self::Reserva => 'purple',
            self::Credito => 'teal',
            self::Vendido => '#ca8a04',
        };
    }

    /**
     * Lo mismo para el valor crudo de la columna, que es lo que reciben los
     * `format()` de las tablas. Un estado que no conozca cae en gris.
     */
    public static function colorDe(?string $estado): string
    {
        return self::tryFrom((string) $estado)?->color() ?? 'gray';
    }

    public static function labelDe(?string $estado): string
    {
        return self::tryFrom((string) $estado)?->label() ?? (string) $estado;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]);
    }

    /**
     * Los estados que el usuario puede ELEGIR: filtra por permiso
     * (producto.estado.<estado> en minuscula) y deja fuera los que solo
     * escribe una venta.
     */
    public static function toSelectArrayPermission(): Collection
    {
        return collect(self::cases())
            ->reject(fn($case) => in_array($case->value, self::soloPorDocumento(), true))
            ->filter(fn($case) => self::validatePermission($case->value))
            ->mapWithKeys(fn($case) => [$case->value => $case->label()]);
    }

    public static function validatePermission($estado)
    {
        $permissionName = 'producto.estado.' . strtolower($estado);
        return Auth::user() && Auth::user()->can($permissionName);
    }
}
