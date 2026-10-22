<?php

declare(strict_types=1);

namespace App\Enum;

/** Forme juridique d'un associé (M10). */
enum PartnerType: string implements LabeledEnum
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Personne physique',
            self::Company => 'Personne morale',
        };
    }

    public function badgeClass(): string
    {
        return 'badge--neutral';
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
