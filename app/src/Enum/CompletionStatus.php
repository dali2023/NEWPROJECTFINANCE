<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut de réalisation d'une échéance contractuelle (§15.6).
 */
enum CompletionStatus: string implements LabeledEnum
{
    case Upcoming = 'upcoming';
    case Due = 'due';
    case Completed = 'completed';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'À venir',
            self::Due => 'Échéance atteinte',
            self::Completed => 'Réalisée',
            self::Suspended => 'Suspendue',
            self::Cancelled => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Upcoming => 'badge--muted',
            self::Due => 'badge--warning',
            self::Completed => 'badge--success',
            self::Suspended => 'badge--neutral',
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

    public function blocksBilling(): bool
    {
        return self::Cancelled === $this || self::Suspended === $this;
    }
}
