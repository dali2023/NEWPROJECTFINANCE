<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type de contrat (§15.2).
 */
enum ContractType: string implements LabeledEnum
{
    case PublicProcurement = 'public_procurement';
    case Private = 'private';
    case Maintenance = 'maintenance';
    case Assistance = 'assistance';
    case License = 'license';
    case Integration = 'integration';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PublicProcurement => 'Marché public',
            self::Private => 'Contrat privé',
            self::Maintenance => 'Maintenance',
            self::Assistance => 'Assistance',
            self::License => 'Licence',
            self::Integration => 'Intégration',
            self::Other => 'Autre',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PublicProcurement => 'badge--info',
            self::Private => 'badge--neutral',
            self::Maintenance => 'badge--primary',
            self::Assistance => 'badge--muted',
            self::License => 'badge--info',
            self::Integration => 'badge--neutral',
            self::Other => 'badge--muted',
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
