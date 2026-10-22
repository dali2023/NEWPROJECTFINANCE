<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type d'événement contractuel déclenchant une facturation (§15.6).
 */
enum MilestoneType: string implements LabeledEnum
{
    case InitialDeposit = 'initial_deposit';
    case Progress = 'progress';
    case LotDelivery = 'lot_delivery';
    case ProvisionalReceipt = 'provisional_receipt';
    case FinalReceipt = 'final_receipt';
    case LicenseDelivery = 'license_delivery';
    case GoLive = 'go_live';
    case MaintenancePeriod = 'maintenance_period';
    case AnnualRenewal = 'annual_renewal';
    case RetentionRelease = 'retention_release';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::InitialDeposit => 'Acompte initial',
            self::Progress => 'Avancement / situation de travaux',
            self::LotDelivery => 'Livraison d’un lot',
            self::ProvisionalReceipt => 'Réception provisoire',
            self::FinalReceipt => 'Réception définitive',
            self::LicenseDelivery => 'Livraison de licence ou prestation',
            self::GoLive => 'Mise en production',
            self::MaintenancePeriod => 'Échéance périodique de maintenance',
            self::AnnualRenewal => 'Renouvellement annuel',
            self::RetentionRelease => 'Libération d’une retenue de garantie',
            self::Other => 'Autre échéance contractuelle',
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

    /** Échéances récurrentes de maintenance, utilisées pour les prévisions. */
    public function isMaintenance(): bool
    {
        return \in_array($this, [self::MaintenancePeriod, self::AnnualRenewal], true);
    }
}
