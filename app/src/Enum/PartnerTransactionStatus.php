<?php

declare(strict_types=1);

namespace App\Enum;

/** Cycle de vie d'une opération d'associé (M10). */
enum PartnerTransactionStatus: string implements LabeledEnum
{
    case Planned = 'planned';
    case Validated = 'validated';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Prévue',
            self::Validated => 'Validée',
            self::Paid => 'Réglée',
            self::Cancelled => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Planned => 'badge--neutral',
            self::Validated => 'badge--primary',
            self::Paid => 'badge--success',
            self::Cancelled => 'badge--danger',
        };
    }

    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }
}
