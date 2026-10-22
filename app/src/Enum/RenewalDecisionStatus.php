<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Décision du client sur un renouvellement (§15.4).
 */
enum RenewalDecisionStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Negotiating = 'negotiating';
    case Accepted = 'accepted';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Contacted => 'Client contacté',
            self::Negotiating => 'En négociation',
            self::Accepted => 'Acceptée',
            self::Refused => 'Refusée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge--muted',
            self::Contacted, self::Negotiating => 'badge--info',
            self::Accepted => 'badge--success',
            self::Refused => 'badge--danger',
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
