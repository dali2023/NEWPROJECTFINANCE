<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\BankAccount;
use App\Entity\Client;
use App\Entity\Contract;
use App\Entity\ContractMilestone;
use App\Entity\Permission;
use App\Entity\Project;
use App\Entity\ProjectType;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\ClientType;
use App\Enum\CompletionStatus;
use App\Enum\ContractBillingStatus;
use App\Enum\ContractStatus;
use App\Enum\ContractType;
use App\Enum\MilestoneType;
use App\Enum\ProjectStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels.
 *
 * Chaque test s'exécute dans une transaction PostgreSQL annulée à la fin : la
 * base de test reste propre d'une exécution à l'autre, sans script de
 * réinitialisation. Le noyau n'est pas redémarré entre les requêtes afin que
 * les écritures HTTP empruntent la même connexion que le test.
 */
abstract class DatabaseTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        $this->entityManager->clear();

        parent::tearDown();
    }

    /**
     * Crée un utilisateur actif porteur du rôle demandé.
     *
     * @param list<string> $permissionCodes permissions unitaires du rôle
     */
    protected function createUser(string $roleCode, array $permissionCodes = []): User
    {
        $suffix = substr(uniqid('', true), -8);

        $role = new Role($roleCode, 'Rôle de test '.$suffix);
        foreach ($permissionCodes as $code) {
            $permission = $this->entityManager->getRepository(Permission::class)->findOneBy(['code' => $code]);
            if (null === $permission) {
                $permission = new Permission($code, 'Permission de test '.$code);
                $this->entityManager->persist($permission);
                $this->entityManager->flush();
            }
            $role->addPermission($permission);
        }
        $this->entityManager->persist($role);

        $user = new User();
        $user->setUsername('test_'.$suffix)
            ->setFullName('Utilisateur '.$suffix)
            ->setEmail('test_'.$suffix.'@one-erp.tn')
            ->setPasswordHash('not-used-in-this-test')
            ->setIsActive(true)
            ->addRole($role);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function createClientFixture(string $name = 'Client de test'): Client
    {
        $suffix = substr(uniqid('', true), -6);

        $client = new Client();
        $client->setCode('CLI-'.$suffix)
            ->setName($name.' '.$suffix)
            ->setClientType(ClientType::Private)
            ->setPaymentTermsDays(30)
            ->setIsActive(true);

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return $client;
    }

    protected function createProjectFixture(Client $client, User $manager, string $name = 'Projet de test'): Project
    {
        $suffix = substr(uniqid('', true), -6);

        $type = new ProjectType();
        $type->setCode('PT-'.$suffix)->setLabel('Type '.$suffix)->setIsActive(true);
        $this->entityManager->persist($type);

        $project = new Project();
        $project->setCode('PRJ-'.$suffix)
            ->setName($name.' '.$suffix)
            ->setClient($client)
            ->setProjectType($type)
            ->setManager($manager)
            ->setCreatedBy($manager)
            ->setStatus(ProjectStatus::InProgress)
            ->setContractAmountHt('100000.000')
            ->setVatRate('19.0000');

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    protected function createContractFixture(
        Client $client,
        User $responsible,
        ?Project $project = null,
        string $amountHt = '100000.000',
        ContractStatus $status = ContractStatus::InProgress,
    ): Contract {
        $suffix = substr(uniqid('', true), -6);

        $contract = new Contract();
        $contract->setReference('CTR-TEST-'.$suffix)
            ->setTitle('Contrat de test '.$suffix)
            ->setContractType(ContractType::Private)
            ->setClient($client)
            ->setProject($project)
            ->setAmountHt($amountHt)
            ->setVatRate('19.0000')
            ->setCurrencyCode('TND')
            ->setResponsible($responsible)
            ->setCreatedBy($responsible)
            ->setNoticePeriodDays(60)
            ->setStatus($status);

        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        return $contract;
    }

    protected function createMilestoneFixture(
        Contract $contract,
        string $amountHt,
        CompletionStatus $completion = CompletionStatus::Completed,
        ContractBillingStatus $billing = ContractBillingStatus::NotBillable,
        ?\DateTimeImmutable $plannedDate = null,
    ): ContractMilestone {
        $suffix = substr(uniqid('', true), -6);

        $milestone = new ContractMilestone();
        $milestone->setReference('ECH-TEST-'.$suffix)
            ->setContract($contract)
            ->setProject($contract->getProject())
            ->setMilestoneType(MilestoneType::Progress)
            ->setLabel('Échéance de test '.$suffix)
            ->setPlannedDate($plannedDate ?? new \DateTimeImmutable('today'))
            ->setAmountHt($amountHt)
            ->setVatRate('19.0000')
            ->setCompletionStatus($completion)
            ->setBillingStatus($billing)
            ->setResponsible($contract->getResponsible());
        $contract->addMilestone($milestone);

        $this->entityManager->persist($milestone);
        $this->entityManager->flush();

        return $milestone;
    }

    protected function createBankAccountFixture(User $user, string $openingBalance = '100000.000'): BankAccount
    {
        $suffix = substr(uniqid('', true), -6);

        $account = new BankAccount();
        $account->setName('Compte de test '.$suffix)
            ->setCurrencyCode('TND')
            ->setOpeningBalance($openingBalance)
            ->setOpeningDate(new \DateTimeImmutable('2026-01-01'))
            ->setIsActive(true)
            ->setCreatedBy($user);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }
}
