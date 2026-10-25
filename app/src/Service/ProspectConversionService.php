<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ProspectConversionData;
use App\Dto\ProspectConversionResult;
use App\Entity\Client;
use App\Entity\Contract;
use App\Entity\Project;
use App\Entity\Prospect;
use App\Entity\User;
use App\Enum\ContractStatus;
use App\Enum\ProjectStatus;
use App\Repository\ClientRepository;
use App\Repository\ContractRepository;
use App\Repository\ProjectRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Conversion transactionnelle d'un prospect.
 *
 * La conversion crée, dans une seule unité de travail :
 * - un client définitif ;
 * - un projet au statut « Opportunité » (le contrat n'est pas encore signé) ;
 * - un contrat brouillon rattaché au client et au projet.
 *
 * Le prospect est verrouillé pendant l'opération et marqué « Converti » à la
 * fin. Une erreur annule l'ensemble : aucun client, projet ou contrat orphelin
 * ne peut être créé.
 */
final class ProspectConversionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReferenceGenerator $referenceGenerator,
        private readonly ClientRepository $clientRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly ContractRepository $contractRepository,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function convert(Prospect $prospect, ProspectConversionData $data, User $actor): ProspectConversionResult
    {
        if ($prospect->isConverted()) {
            throw new \DomainException('Ce prospect a déjà été converti.');
        }
        if (!$prospect->canConvert()) {
            throw new \DomainException('Un prospect perdu ne peut pas être converti. Modifiez d’abord son statut.');
        }
        if (null === $data->getProjectType()) {
            throw new \DomainException('Le type de projet est obligatoire pour convertir le prospect.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($prospect, $data, $actor): ProspectConversionResult {
            // Verrou pessimiste : deux conversions simultanées ne peuvent pas
            // créer deux jeux de client/projet/contrat.
            $this->entityManager->refresh($prospect, LockMode::PESSIMISTIC_WRITE);

            if ($prospect->isConverted()) {
                throw new \DomainException('Ce prospect a déjà été converti.');
            }
            if (!$prospect->canConvert()) {
                throw new \DomainException('Un prospect perdu ne peut pas être converti.');
            }

            $today = new \DateTimeImmutable('today');

            $clientCode = $this->nextUniqueReference(
                ReferenceGenerator::MODULE_CLIENT,
                fn (string $code): bool => $this->clientRepository->codeExists($code),
            );
            $client = new Client();
            $client->setCode($clientCode)
                ->setName($prospect->getName())
                ->setClientType($data->getClientType())
                ->setTaxIdentifier($prospect->getTaxIdentifier())
                ->setContactName($prospect->getContactName())
                ->setEmail($prospect->getEmail())
                ->setPhone($prospect->getPhone())
                ->setAddress($prospect->getAddress())
                ->setCity($prospect->getCity())
                ->setPostalCode($prospect->getPostalCode())
                ->setCountry($prospect->getCountry() ?? 'Tunisie')
                ->setPaymentTermsDays($data->getPaymentTermsDays())
                ->setIsActive(true)
                ->setNotes($this->clientNotes($prospect, $today))
                ->setCreatedBy($actor);

            $projectCode = $this->nextUniqueReference(
                ReferenceGenerator::MODULE_PROJECT,
                fn (string $code): bool => $this->projectRepository->codeExists($code),
            );
            $manager = $data->getProjectManager() ?? $actor;
            $project = new Project();
            $project->setCode($projectCode)
                ->setName($data->getProjectName())
                ->setClient($client)
                ->setProjectType($data->getProjectType())
                ->setManager($manager)
                ->setStatus(ProjectStatus::Opportunity)
                ->setCurrencyCode($data->getCurrencyCode())
                ->setContractAmountHt($data->getContractAmountHt())
                ->setVatRate($data->getVatRate())
                ->setBudgetAmount('0.000')
                ->setPaymentTerms(sprintf('%d jours', $data->getPaymentTermsDays()))
                ->setProbability($prospect->getProbability())
                ->setNotes($this->projectNotes($prospect, $today))
                ->setCreatedBy($actor);
            $project->recomputeTotals();

            $contractReference = $this->nextUniqueReference(
                ReferenceGenerator::MODULE_CONTRACT,
                fn (string $reference): bool => $this->contractRepository->referenceExists($reference),
            );
            $contract = new Contract();
            $contract->setReference($contractReference)
                ->setTitle($data->getContractTitle())
                ->setContractType($data->getContractType())
                ->setClient($client)
                ->setProject($project)
                ->setAmountHt($data->getContractAmountHt())
                ->setVatRate($data->getVatRate())
                ->setCurrencyCode($data->getCurrencyCode())
                ->setResponsible($manager)
                ->setPaymentTerms(sprintf('%d jours', $data->getPaymentTermsDays()))
                ->setStatus(ContractStatus::Draft)
                ->setNotes($this->contractNotes($prospect, $today))
                ->setCreatedBy($actor);
            $contract->recomputeTotals();

            $previousStatus = $prospect->getStatus();
            $prospect->markConverted($client, $project, $contract, $actor);

            $this->entityManager->persist($client);
            $this->entityManager->persist($project);
            $this->entityManager->persist($contract);

            $this->auditLogger->log(
                'create',
                'Client',
                $client->getIdString(),
                null,
                [
                    'code' => $client->getCode(),
                    'name' => $client->getName(),
                    'sourceProspect' => $prospect->getReference(),
                ],
                'success',
                'M03',
                'Création d’un client par conversion d’un prospect',
                flush: false,
            );
            $this->auditLogger->log(
                'create',
                'Project',
                $project->getIdString(),
                null,
                [
                    'code' => $project->getCode(),
                    'name' => $project->getName(),
                    'status' => $project->getStatus()->value,
                    'sourceProspect' => $prospect->getReference(),
                ],
                'success',
                'M03',
                'Création d’un projet par conversion d’un prospect',
                flush: false,
            );
            $this->auditLogger->log(
                'create',
                'Contract',
                $contract->getIdString(),
                null,
                [
                    'reference' => $contract->getReference(),
                    'title' => $contract->getTitle(),
                    'status' => $contract->getStatus()->value,
                    'sourceProspect' => $prospect->getReference(),
                ],
                'success',
                'M15',
                'Création d’un contrat brouillon par conversion d’un prospect',
                flush: false,
            );
            $this->auditLogger->log(
                'convert',
                'Prospect',
                $prospect->getIdString(),
                ['status' => $previousStatus->value, 'converted' => false],
                [
                    'status' => $prospect->getStatus()->value,
                    'converted' => true,
                    'client' => $client->getCode(),
                    'project' => $project->getCode(),
                    'contract' => $contract->getReference(),
                ],
                'success',
                'M03',
                'Conversion du prospect en client, projet et contrat brouillon',
                flush: false,
            );

            return new ProspectConversionResult($client, $project, $contract);
        });
    }

    /**
     * @param callable(string): bool $exists
     */
    private function nextUniqueReference(string $module, callable $exists): string
    {
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $reference = $this->referenceGenerator->next($module);
            if (!$exists($reference)) {
                return $reference;
            }
        }

        throw new \RuntimeException('Impossible de générer une référence unique. Veuillez réessayer.');
    }

    private function clientNotes(Prospect $prospect, \DateTimeImmutable $today): string
    {
        $notes = sprintf('Créé par conversion du prospect %s le %s.', $prospect->getReference(), $today->format('d/m/Y'));
        if (null !== $prospect->getNotes()) {
            $notes .= "\n".$prospect->getNotes();
        }

        return $notes;
    }

    private function projectNotes(Prospect $prospect, \DateTimeImmutable $today): string
    {
        return sprintf(
            'Projet créé par conversion du prospect %s le %s. Le contrat brouillon est rattaché à ce projet.',
            $prospect->getReference(),
            $today->format('d/m/Y'),
        );
    }

    private function contractNotes(Prospect $prospect, \DateTimeImmutable $today): string
    {
        $notes = sprintf('Contrat brouillon créé par conversion du prospect %s le %s.', $prospect->getReference(), $today->format('d/m/Y'));
        if (null !== $prospect->getNotes()) {
            $notes .= "\n".$prospect->getNotes();
        }

        return $notes;
    }
}
