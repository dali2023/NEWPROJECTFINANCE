<?php

declare(strict_types=1);

namespace App\Enum;

/** Moyen de règlement (M04, M05, M09). */
enum PaymentMethod: string implements LabeledEnum
{
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Cash = 'cash';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Virement bancaire',
            self::Cheque => 'Chèque',
            self::Cash => 'Espèces',
            self::Card => 'Carte bancaire',
            self::Other => 'Autre',
        };
    }

    public function badgeClass(): string
    {
        return 'badge--neutral';
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
