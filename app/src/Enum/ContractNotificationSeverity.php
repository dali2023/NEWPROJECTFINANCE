<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Priorité d'une alerte, dérivée du délai de déclenchement (§15.7).
 */
enum ContractNotificationSeverity: string implements LabeledEnum
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Information',
            self::Warning => 'Avertissement',
            self::Critical => 'Critique',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Info => 'badge--info',
            self::Warning => 'badge--warning',
            self::Critical => 'badge--danger',
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

    public static function forDelay(int $delay): self
    {
        if ($delay <= 7) {
            return self::Critical;
        }

        if ($delay <= 30) {
            return self::Warning;
        }

        return self::Info;
    }
}
