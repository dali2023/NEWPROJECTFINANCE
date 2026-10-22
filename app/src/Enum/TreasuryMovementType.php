<?php

declare(strict_types=1);

namespace App\Enum;

/** Origine d'un mouvement de trésorerie (M09). */
enum TreasuryMovementType: string implements LabeledEnum
{
    case ClientReceipt = 'client_receipt';
    case SupplierPayment = 'supplier_payment';
    case ExpensePayment = 'expense_payment';
    case PartnerContribution = 'partner_contribution';
    case PartnerRepayment = 'partner_repayment';
    case Dividend = 'dividend';
    case Transfer = 'transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ClientReceipt => 'Encaissement client',
            self::SupplierPayment => 'Paiement fournisseur',
            self::ExpensePayment => 'Paiement de charge',
            self::PartnerContribution => 'Apport d’associé',
            self::PartnerRepayment => 'Remboursement d’associé',
            self::Dividend => 'Distribution de dividendes',
            self::Transfer => 'Virement interne',
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
