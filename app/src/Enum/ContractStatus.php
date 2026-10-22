<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'un contrat (§15.2).
 *
 * Les statuts « À renouveler », « Expirant prochainement », « Expiré » et
 * « En maintenance active » peuvent être calculés à partir des dates. Ils ne
 * déclenchent jamais un renouvellement : celui-ci exige une action autorisée.
 */
enum ContractStatus: string implements LabeledEnum
{
    case Draft = 'draft';
    case PendingSignature = 'pending_signature';
    case Signed = 'signed';
    case InProgress = 'in_progress';
    case Warranty = 'warranty';
    case ActiveMaintenance = 'active_maintenance';
    case ToRenew = 'to_renew';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::PendingSignature => 'En attente de signature',
            self::Signed => 'Signé',
            self::InProgress => 'En cours d’exécution',
            self::Warranty => 'En période de garantie',
            self::ActiveMaintenance => 'En maintenance active',
            self::ToRenew => 'À renouveler',
            self::Expiring => 'Expirant prochainement',
            self::Expired => 'Expiré',
            self::Terminated => 'Résilié',
            self::Closed => 'Clôturé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge--muted',
            self::PendingSignature => 'badge--warning',
            self::Signed => 'badge--info',
            self::InProgress => 'badge--primary',
            self::Warranty => 'badge--neutral',
            self::ActiveMaintenance => 'badge--primary',
            self::ToRenew => 'badge--warning',
            self::Expiring => 'badge--warning',
            self::Expired => 'badge--danger',
            self::Terminated => 'badge--danger',
            self::Closed => 'badge--muted',
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

    /** Statuts contractuels : le contrat engage juridiquement ONE ERP. */
    public function isContractual(): bool
    {
        return \in_array($this, [
            self::Signed,
            self::InProgress,
            self::Warranty,
            self::ActiveMaintenance,
            self::ToRenew,
            self::Expiring,
        ], true);
    }

    /** Un contrat figé ne peut plus être modifié sans opération exceptionnelle. */
    public function isFrozen(): bool
    {
        return self::Closed === $this || self::Terminated === $this;
    }
}
