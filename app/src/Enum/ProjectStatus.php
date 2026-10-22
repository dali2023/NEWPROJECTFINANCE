<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cycle de vie d'un projet (§4.3).
 *
 * La distinction entre « opportunité » et « contrat signé » est structurante :
 * un projet perdu ou en cours de prospection ne doit jamais être comptabilisé
 * dans les contrats signés (§8.1).
 */
enum ProjectStatus: string implements LabeledEnum
{
    case Draft = 'draft';
    case CommercialPreparation = 'commercial_preparation';
    case Opportunity = 'opportunity';
    case Won = 'won';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Delivered = 'delivered';
    case Closed = 'closed';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::CommercialPreparation => 'En préparation commerciale',
            self::Opportunity => 'Opportunité',
            self::Won => 'Gagné / contrat signé',
            self::InProgress => 'En cours d’exécution',
            self::OnHold => 'En attente / suspendu',
            self::Delivered => 'Livré',
            self::Closed => 'Clôturé',
            self::Lost => 'Abandonné / perdu',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge--muted',
            self::CommercialPreparation => 'badge--neutral',
            self::Opportunity => 'badge--info',
            self::Won => 'badge--success',
            self::InProgress => 'badge--primary',
            self::OnHold => 'badge--warning',
            self::Delivered => 'badge--success',
            self::Closed => 'badge--muted',
            self::Lost => 'badge--danger',
        };
    }

    /** Le projet est-il un contrat signé (donc un produit contractuel) ? */
    public function isContractual(): bool
    {
        return \in_array($this, [self::Won, self::InProgress, self::OnHold, self::Delivered, self::Closed], true);
    }

    /** Le projet est-il encore une opportunité commerciale ? */
    public function isCommercialPipeline(): bool
    {
        return \in_array($this, [self::Draft, self::CommercialPreparation, self::Opportunity], true);
    }

    /** Le projet est-il terminé (plus de mouvements attendus) ? */
    public function isFinished(): bool
    {
        return \in_array($this, [self::Closed, self::Lost], true);
    }

    /** Statuts accessibles depuis un statut donné (§8.1). */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::CommercialPreparation, self::Opportunity, self::Lost],
            self::CommercialPreparation => [self::Opportunity, self::Won, self::Lost],
            self::Opportunity => [self::Won, self::InProgress, self::Lost],
            self::Won => [self::InProgress, self::OnHold, self::Lost],
            self::InProgress => [self::OnHold, self::Delivered, self::Closed, self::Lost],
            self::OnHold => [self::InProgress, self::Delivered, self::Closed, self::Lost],
            self::Delivered => [self::InProgress, self::Closed],
            self::Closed => [],
            self::Lost => [self::Opportunity],
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
}
