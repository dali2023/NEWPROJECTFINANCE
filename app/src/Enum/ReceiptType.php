<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type de réception contractuelle (§15.5).
 */
enum ReceiptType: string implements LabeledEnum
{
    case Provisional = 'provisional';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Provisional => 'Réception provisoire',
            self::Final => 'Réception définitive',
        };
    }

    public function badgeClass(): string
    {
        return self::Provisional === $this ? 'badge--info' : 'badge--primary';
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
