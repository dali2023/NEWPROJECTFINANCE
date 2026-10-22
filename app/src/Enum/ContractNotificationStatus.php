<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cycle de vie d'une notification (§15.7).
 */
enum ContractNotificationStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À traiter',
            self::InProgress => 'Prise en charge',
            self::Resolved => 'Résolue',
            self::Dismissed => 'Sans objet',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge--warning',
            self::InProgress => 'badge--info',
            self::Resolved => 'badge--success',
            self::Dismissed => 'badge--muted',
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

    public function isActive(): bool
    {
        return self::Pending === $this || self::InProgress === $this;
    }
}
