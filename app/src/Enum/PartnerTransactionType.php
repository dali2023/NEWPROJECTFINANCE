<?php

declare(strict_types=1);

namespace App\Enum;

/** Nature d'une opération avec un associé (M10). */
enum PartnerTransactionType: string implements LabeledEnum
{
    case CapitalContribution = 'capital_contribution';
    case CurrentAccountContribution = 'current_account_contribution';
    case CurrentAccountRepayment = 'current_account_repayment';
    case Dividend = 'dividend';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CapitalContribution => 'Apport en capital',
            self::CurrentAccountContribution => 'Apport en compte courant',
            self::CurrentAccountRepayment => 'Remboursement de compte courant',
            self::Dividend => 'Dividende',
            self::Other => 'Autre opération',
        };
    }

    public function badgeClass(): string
    {
        return 'badge--neutral';
    }

    /** Sens du flux pour la trésorerie. */
    public function isInflow(): bool
    {
        return \in_array($this, [self::CapitalContribution, self::CurrentAccountContribution], true);
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
