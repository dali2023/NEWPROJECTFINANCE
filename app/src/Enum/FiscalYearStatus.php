<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'un exercice financier (§4.1).
 *
 * Un exercice clôturé ne peut plus être modifié par les utilisateurs ordinaires
 * (§5.4, REC-014) : les règles de garde sont portées par
 * App\Service\FiscalYearGuard.
 */
enum FiscalYearStatus: string implements LabeledEnum
{
    case Open = 'open';
    case Closing = 'closing';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::Closing => 'En préparation de clôture',
            self::Closed => 'Clôturé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'badge--success',
            self::Closing => 'badge--warning',
            self::Closed => 'badge--muted',
        };
    }

    public function acceptsOrdinaryWrites(): bool
    {
        return self::Open === $this;
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
