<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Por que se dio de baja un equipo o unas unidades de stock.
 *
 * Los values van sin tildes (como 'Reparacion' en ProductoEstado): la colacion
 * utf8mb4_unicode_ci iguala tildes en un WHERE y un value con tilde invita a
 * que dos escrituras distintas cuenten como el mismo motivo. La tilde vive en
 * label().
 */
enum BajaMotivo: string
{
    case Dano = 'Dano';
    case Perdida = 'Perdida';
    case Robo = 'Robo';
    case Defecto = 'Defecto';
    case Otro = 'Otro';

    public function label(): string
    {
        return match ($this) {
            self::Dano => 'Daño',
            self::Perdida => 'Pérdida',
            self::Robo => 'Robo',
            self::Defecto => 'Defecto de fábrica',
            self::Otro => 'Otro',
        };
    }

    public static function labelDe(?string $motivo): string
    {
        return self::tryFrom((string) $motivo)?->label() ?? (string) $motivo;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($c) => [$c->value => $c->label()]);
    }
}
