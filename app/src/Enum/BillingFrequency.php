<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Périodicité de facturation d'un contrat de maintenance (§15.3).
 */
enum BillingFrequency: string implements LabeledEnum
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensuelle',
            self::Quarterly => 'Trimestrielle',
            self::SemiAnnual => 'Semestrielle',
            self::Annual => 'Annuelle',
        };
    }

    public function badgeClass(): string
    {
        return 'badge--neutral';
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::SemiAnnual => 6,
            self::Annual => 12,
        };
    }

    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Quarterly => 4,
            self::SemiAnnual => 2,
            self::Annual => 1,
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
