<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut de facturation d'une échéance contractuelle (§15.6).
 *
 * « Prête à facturer » ne signifie pas « facturée » : la facture est émise par
 * le processus de facturation (phase 3) et sa référence est ensuite reportée.
 */
enum ContractBillingStatus: string implements LabeledEnum
{
    case NotBillable = 'not_billable';
    case ToReview = 'to_review';
    case ReadyToInvoice = 'ready_to_invoice';
    case InvoiceIssued = 'invoice_issued';
    case PartiallyInvoiced = 'partially_invoiced';
    case FullyInvoiced = 'fully_invoiced';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::NotBillable => 'Non facturable à ce stade',
            self::ToReview => 'À vérifier',
            self::ReadyToInvoice => 'Prête à facturer',
            self::InvoiceIssued => 'Facture émise',
            self::PartiallyInvoiced => 'Partiellement facturée',
            self::FullyInvoiced => 'Facturée intégralement',
            self::Blocked => 'Bloquée pour motif contractuel',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotBillable => 'badge--muted',
            self::ToReview => 'badge--info',
            self::ReadyToInvoice => 'badge--warning',
            self::InvoiceIssued => 'badge--primary',
            self::PartiallyInvoiced => 'badge--warning',
            self::FullyInvoiced => 'badge--success',
            self::Blocked => 'badge--danger',
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

    public function isFullyInvoiced(): bool
    {
        return self::FullyInvoiced === $this;
    }

    public function blocksBilling(): bool
    {
        return self::Blocked === $this;
    }
}
