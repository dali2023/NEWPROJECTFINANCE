<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * État de renouvellement d'un contrat de maintenance (§15.3, §15.4).
 */
enum MaintenanceRenewalStatus: string implements LabeledEnum
{
    case NotDue = 'not_due';
    case ToPrepare = 'to_prepare';
    case InNegotiation = 'in_negotiation';
    case Renewed = 'renewed';
    case NotRenewed = 'not_renewed';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::NotDue => 'Non échéant',
            self::ToPrepare => 'À préparer',
            self::InNegotiation => 'En négociation',
            self::Renewed => 'Renouvelé',
            self::NotRenewed => 'Non renouvelé',
            self::Terminated => 'Résilié',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotDue => 'badge--muted',
            self::ToPrepare => 'badge--warning',
            self::InNegotiation => 'badge--info',
            self::Renewed => 'badge--success',
            self::NotRenewed => 'badge--danger',
            self::Terminated => 'badge--danger',
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
