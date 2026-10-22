<?php

declare(strict_types=1);

namespace App\Enum;

/** Nature d'un document de facturation (M04). */
enum InvoiceType: string implements LabeledEnum
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Facture',
            self::CreditNote => 'Avoir',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Invoice => 'badge--primary',
            self::CreditNote => 'badge--warning',
        };
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
