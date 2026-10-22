<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Mode de renouvellement contractuel (§15.3).
 *
 * Même lorsqu'une clause prévoit un renouvellement automatique, l'application
 * n'enregistre jamais un renouvellement sans action autorisée : le mode décrit
 * seulement la clause, il ne l'exécute pas.
 */
enum RenewalMode: string implements LabeledEnum
{
    case Manual = 'manual';
    case Automatic = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuel',
            self::Automatic => 'Automatique (clause contractuelle)',
        };
    }

    public function badgeClass(): string
    {
        return self::Manual === $this ? 'badge--neutral' : 'badge--warning';
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
