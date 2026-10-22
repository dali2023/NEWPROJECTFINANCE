<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * État d'une retenue de garantie (§15.5, §15.10 F).
 */
enum RetentionStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case ReleaseRequested = 'release_requested';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Eligible => 'Éligible à libération',
            self::ReleaseRequested => 'Libération demandée',
            self::Released => 'Libérée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge--muted',
            self::Eligible => 'badge--warning',
            self::ReleaseRequested => 'badge--info',
            self::Released => 'badge--success',
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
