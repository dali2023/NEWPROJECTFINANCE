<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Nature d'un document contractuel (§15.2, §15.5).
 */
enum ContractDocumentType: string implements LabeledEnum
{
    case Contract = 'contract';
    case Amendment = 'amendment';
    case ReceiptReport = 'receipt_report';
    case Invoice = 'invoice';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contrat',
            self::Amendment => 'Avenant',
            self::ReceiptReport => 'Procès-verbal de réception',
            self::Invoice => 'Facture',
            self::Other => 'Autre document',
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
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}
