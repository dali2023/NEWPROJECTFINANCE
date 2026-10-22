<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Enum\RenewalDecisionStatus;

/**
 * Données saisies lors de l'enregistrement d'un renouvellement (§15.4).
 */
final class ContractRenewalData
{
    public ?\DateTimeImmutable $newStartDate = null;
    public ?\DateTimeImmutable $newEndDate = null;
    public string $newAmountHt = '0.000';
    public RenewalDecisionStatus $decisionStatus = RenewalDecisionStatus::Accepted;
    public ?User $responsible = null;
    public ?string $notes = null;
    public bool $generateMilestones = true;
}
