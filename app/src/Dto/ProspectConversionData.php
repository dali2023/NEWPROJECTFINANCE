<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\ProjectType;
use App\Entity\User;
use App\Enum\ClientType;
use App\Enum\ContractType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paramètres saisis lors de la conversion d'un prospect.
 */
final class ProspectConversionData
{
    #[Assert\NotBlank(message: 'Le type de client est obligatoire.')]
    public ClientType $clientType = ClientType::Private;

    #[Assert\Range(min: 0, max: 365, notInRangeMessage: 'Le délai de paiement doit être compris entre 0 et 365 jours.')]
    public int $paymentTermsDays = 30;

    #[Assert\NotNull(message: 'Le type de projet est obligatoire.')]
    public ?ProjectType $projectType = null;

    public ?User $projectManager = null;

    #[Assert\NotBlank(message: 'L’intitulé du projet est obligatoire.')]
    #[Assert\Length(max: 200, maxMessage: 'L’intitulé du projet ne peut pas dépasser {{ limit }} caractères.')]
    public string $projectName = '';

    #[Assert\NotBlank(message: 'L’intitulé du contrat est obligatoire.')]
    #[Assert\Length(max: 200, maxMessage: 'L’intitulé du contrat ne peut pas dépasser {{ limit }} caractères.')]
    public string $contractTitle = '';

    #[Assert\NotNull(message: 'Le type de contrat est obligatoire.')]
    public ContractType $contractType = ContractType::Private;

    #[Assert\NotBlank(message: 'Le montant HT est obligatoire.')]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,3})?$/', message: 'Montant HT invalide.')]
    public string $contractAmountHt = '0.000';

    #[Assert\NotBlank(message: 'Le taux de TVA est obligatoire.')]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,4})?$/', message: 'Taux de TVA invalide.')]
    #[Assert\LessThanOrEqual(100, message: 'Le taux de TVA ne peut pas dépasser 100 %.')]
    public string $vatRate = '19.0000';

    #[Assert\NotBlank(message: 'La devise est obligatoire.')]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Devise invalide : code ISO à trois lettres attendu.')]
    public string $currencyCode = 'TND';

    public function getClientType(): ClientType
    {
        return $this->clientType;
    }

    public function setClientType(ClientType $clientType): static
    {
        $this->clientType = $clientType;

        return $this;
    }

    public function getPaymentTermsDays(): int
    {
        return $this->paymentTermsDays;
    }

    public function setPaymentTermsDays(int $paymentTermsDays): static
    {
        $this->paymentTermsDays = $paymentTermsDays;

        return $this;
    }

    public function getProjectType(): ?ProjectType
    {
        return $this->projectType;
    }

    public function setProjectType(?ProjectType $projectType): static
    {
        $this->projectType = $projectType;

        return $this;
    }

    public function getProjectManager(): ?User
    {
        return $this->projectManager;
    }

    public function setProjectManager(?User $projectManager): static
    {
        $this->projectManager = $projectManager;

        return $this;
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function setProjectName(string $projectName): static
    {
        $this->projectName = $projectName;

        return $this;
    }

    public function getContractTitle(): string
    {
        return $this->contractTitle;
    }

    public function setContractTitle(string $contractTitle): static
    {
        $this->contractTitle = $contractTitle;

        return $this;
    }

    public function getContractType(): ContractType
    {
        return $this->contractType;
    }

    public function setContractType(ContractType $contractType): static
    {
        $this->contractType = $contractType;

        return $this;
    }

    public function getContractAmountHt(): string
    {
        return $this->contractAmountHt;
    }

    public function setContractAmountHt(string $contractAmountHt): static
    {
        $this->contractAmountHt = $contractAmountHt;

        return $this;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): static
    {
        $this->vatRate = $vatRate;

        return $this;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): static
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }
}
