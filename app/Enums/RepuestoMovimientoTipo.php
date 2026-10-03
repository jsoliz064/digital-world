<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Tipos de movimiento del historial de un repuesto.
 *
 * OJO: los `value` de este enum se escriben LITERALMENTE dentro del UNION de
 * RepuestoMovimiento::paraRepuesto() ("'Compra' as tipo"). Si cambias un value
 * aquí y no allí, el filtro deja de encontrar filas silenciosamente.
 */
enum RepuestoMovimientoTipo: string
{
    case Compra = 'Compra';
    case Venta = 'Venta';
    case Reparacion = 'Reparacion';
    case Transferencia = 'Transferencia';

    public function label(): string
    {
        return match ($this) {
            self::Compra => 'Compra',
            self::Venta => 'Venta',
            self::Reparacion => 'Reparación',
            self::Transferencia => 'Transferencia',
        };
    }

    /**
     * Redundante con el tipo a propósito: espeja la columna `direccion` del UNION.
     *
     * Devuelve null para Transferencia, que no tiene una sola dirección: sale de
     * una sucursal y entra en otra, y el UNION la parte en DOS filas opuestas.
     * Antes era `$this === self::Compra ? 'Entrada' : 'Salida'`, que para una
     * transferencia habría contestado 'Salida' -- media verdad, y la peor mitad.
     */
    public function direccion(): ?string
    {
        return match ($this) {
            self::Compra => 'Entrada',
            self::Venta, self::Reparacion => 'Salida',
            self::Transferencia => null,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Compra => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Venta => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::Reparacion => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            self::Transferencia => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return Collection<string,string> value => label (opciones del filtro) */
    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]);
    }
}
