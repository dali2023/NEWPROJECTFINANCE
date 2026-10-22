<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * État global des réserves d'une réception (§15.5).
 */
enum ReservationsStatus: string implements LabeledEnum
{
    case None = 'none';
    case Open = 'open';
    case PartiallyLifted = 'partially_lifted';
    case Lifted = 'lifted';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucune réserve',
            self::Open => 'Réserves ouvertes',
            self::PartiallyLifted => 'Partiellement levées',
            self::Lifted => 'Levées',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::None => 'badge--muted',
            self::Open => 'badge--danger',
            self::PartiallyLifted => 'badge--warning',
            self::Lifted => 'badge--success',
        };
    }

    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}
