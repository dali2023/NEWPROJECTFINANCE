<?php

declare(strict_types=1);

namespace App\Enum;

/** Cycle de vie d'une charge (M05). */
enum ExpenseStatus: string implements LabeledEnum
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Submitted => 'Soumise',
            self::Approved => 'Approuvée',
            self::Rejected => 'Rejetée',
            self::PartiallyPaid => 'Partiellement payée',
            self::Paid => 'Payée',
            self::Cancelled => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge--neutral',
            self::Submitted => 'badge--info',
            self::Approved => 'badge--primary',
            self::Rejected => 'badge--danger',
            self::PartiallyPaid => 'badge--warning',
            self::Paid => 'badge--success',
            self::Cancelled => 'badge--danger',
        };
    }

    public function isEditable(): bool
    {
        return \in_array($this, [self::Draft, self::Submitted], true);
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
