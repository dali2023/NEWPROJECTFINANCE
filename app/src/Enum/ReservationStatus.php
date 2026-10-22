<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * État d'une réserve individuelle (§15.5).
 */
enum ReservationStatus: string implements LabeledEnum
{
    case Open = 'open';
    case Lifted = 'lifted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouverte',
            self::Lifted => 'Levée',
            self::Cancelled => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'badge--danger',
            self::Lifted => 'badge--success',
            self::Cancelled => 'badge--muted',
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
