<?php

declare(strict_types=1);

namespace App\Enum;

/** Nature d'une charge ou dépense (M05). */
enum ExpenseCategory: string implements LabeledEnum
{
    case Subcontracting = 'subcontracting';
    case Purchases = 'purchases';
    case Travel = 'travel';
    case Personnel = 'personnel';
    case Equipment = 'equipment';
    case Overhead = 'overhead';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Subcontracting => 'Sous-traitance',
            self::Purchases => 'Achats et fournitures',
            self::Travel => 'Déplacements',
            self::Personnel => 'Personnel',
            self::Equipment => 'Équipement',
            self::Overhead => 'Frais généraux',
            self::Other => 'Autre',
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
