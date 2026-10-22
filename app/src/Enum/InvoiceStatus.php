<?php

declare(strict_types=1);

namespace App\Enum;

/** Cycle de vie d'une facture (M04). */
enum InvoiceStatus: string implements LabeledEnum
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Validated => 'Validée',
            self::Sent => 'Envoyée',
            self::PartiallyPaid => 'Partiellement réglée',
            self::Paid => 'Réglée',
            self::Cancelled => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge--neutral',
            self::Validated => 'badge--primary',
            self::Sent => 'badge--info',
            self::PartiallyPaid => 'badge--warning',
            self::Paid => 'badge--success',
            self::Cancelled => 'badge--danger',
        };
    }

    public function isEditable(): bool
    {
        return self::Draft === $this;
    }

    public function isOpen(): bool
    {
        return \in_array($this, [self::Validated, self::Sent, self::PartiallyPaid], true);
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
