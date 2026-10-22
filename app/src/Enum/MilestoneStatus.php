<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'un jalon contractuel (§5.2 G — project_milestones).
 */
enum MilestoneStatus: string implements LabeledEnum
{
    case Planned = 'planned';
    case Reached = 'reached';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Prévu',
            self::Reached => 'Atteint',
            self::Invoiced => 'Facturé',
            self::Paid => 'Encaissé',
            self::Cancelled => 'Annulé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Planned => 'badge--muted',
            self::Reached => 'badge--info',
            self::Invoiced => 'badge--primary',
            self::Paid => 'badge--success',
            self::Cancelled => 'badge--danger',
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
