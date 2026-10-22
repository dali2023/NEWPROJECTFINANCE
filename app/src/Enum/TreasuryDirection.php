<?php

declare(strict_types=1);

namespace App\Enum;

/** Sens d'un mouvement de trésorerie (M09). */
enum TreasuryDirection: string implements LabeledEnum
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';

    public function label(): string
    {
        return match ($this) {
            self::Inflow => 'Encaissement',
            self::Outflow => 'Décaissement',
        };
    }

    public function badgeClass(): string
    {
        return self::Inflow === $this ? 'badge--success' : 'badge--danger';
    }

    public function sign(): int
    {
        return self::Inflow === $this ? 1 : -1;
    }

    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }
}
