<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type d'événement à l'origine d'une notification contractuelle (§15.7).
 */
enum ContractNotificationType: string implements LabeledEnum
{
    case ContractExpiration = 'contract_expiration';
    case MaintenanceRenewal = 'maintenance_renewal';
    case ProvisionalReceipt = 'provisional_receipt';
    case FinalReceipt = 'final_receipt';
    case BillingDue = 'billing_due';
    case ReservationLifting = 'reservation_lifting';
    case WarrantyEnd = 'warranty_end';
    case RetentionRelease = 'retention_release';

    public function label(): string
    {
        return match ($this) {
            self::ContractExpiration => 'Expiration d’un contrat',
            self::MaintenanceRenewal => 'Renouvellement de maintenance',
            self::ProvisionalReceipt => 'Réception provisoire prévue',
            self::FinalReceipt => 'Réception définitive prévue',
            self::BillingDue => 'Échéance de facturation',
            self::ReservationLifting => 'Levée des réserves',
            self::WarrantyEnd => 'Fin de garantie',
            self::RetentionRelease => 'Libération d’une retenue de garantie',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ContractExpiration, self::BillingDue => 'badge--warning',
            self::MaintenanceRenewal, self::FinalReceipt => 'badge--info',
            self::ProvisionalReceipt, self::ReservationLifting => 'badge--primary',
            self::WarrantyEnd => 'badge--neutral',
            self::RetentionRelease => 'badge--success',
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

    /**
     * Délais par défaut, en jours avant l'échéance.
     * Une valeur négative représente une alerte après l'échéance.
     *
     * @return list<int>
     */
    public function defaultDelays(): array
    {
        return match ($this) {
            self::ContractExpiration => [90, 60, 30, 7],
            self::MaintenanceRenewal => [90, 60, 30],
            self::ProvisionalReceipt => [30, 7, 0],
            self::FinalReceipt => [60, 30, 7, 0],
            self::BillingDue => [15, 7, 0, -1],
            self::ReservationLifting => [15, 7, 0],
            self::WarrantyEnd => [60, 30, 7],
            self::RetentionRelease => [0, -1],
        };
    }
}
