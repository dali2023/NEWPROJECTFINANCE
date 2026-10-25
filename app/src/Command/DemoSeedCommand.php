<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BankAccount;
use App\Entity\Client;
use App\Entity\Contract;
use App\Entity\ContractMilestone;
use App\Entity\Expense;
use App\Entity\FiscalYear;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\MaintenanceContract;
use App\Entity\Partner;
use App\Entity\PartnerTransaction;
use App\Entity\Permission;
use App\Entity\Project;
use App\Entity\ProjectMilestone;
use App\Entity\ProjectType;
use App\Entity\Role;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\BillingFrequency;
use App\Enum\ClientType;
use App\Enum\CompletionStatus;
use App\Enum\ContractBillingStatus;
use App\Enum\ContractStatus;
use App\Enum\ContractType;
use App\Enum\ExpenseCategory;
use App\Enum\ExpenseStatus;
use App\Enum\FiscalYearStatus;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Enum\MaintenanceRenewalStatus;
use App\Enum\MilestoneStatus;
use App\Enum\MilestoneType;
use App\Enum\PartnerTransactionStatus;
use App\Enum\PartnerTransactionType;
use App\Enum\PartnerType;
use App\Enum\PaymentMethod;
use App\Enum\ProjectStatus;
use App\Enum\RenewalMode;
use App\Service\Money;
use App\Service\ReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de démonstration (§16 : jeu d'essai).
 *
 * Le commandement est idempotent : il complète les données manquantes sans
 * écraser les modifications faites depuis l'interface. L'option --reset
 * supprime toutes les données métier (usage strictement réservé au poste de
 * développement).
 */
#[AsCommand(
    name: 'app:demo:seed',
    description: 'Crée les permissions, rôles, comptes et données de démonstration.',
)]
final class DemoSeedCommand extends Command
{
    private const DEFAULT_PASSWORD = 'Demo1234!';

    /**
     * Catalogue des permissions unitaires (docs/03-matrice-permissions.md).
     *
     * @var array<string, array{0: string, 1: string, 2?: string}>
     */
    private const PERMISSIONS = [
        'admin.users' => ['Gérer les comptes utilisateurs', 'M01', 'Création, modification, désactivation des comptes et attribution des rôles.'],
        'admin.referentials' => ['Gérer les référentiels', 'M02', 'Types de projet, exercices financiers, catégories.'],
        'admin.allocation_rules' => ['Gérer les règles de répartition', 'M02', 'Clés de répartition des charges indirectes.'],
        'admin.audit' => ['Consulter le journal d’audit', 'M14', 'Lecture seule du journal des opérations sensibles.'],
        'admin.settings' => ['Paramétrer la société et les emails', 'M01', 'Identité, logo, coordonnées et serveur SMTP des rappels contractuels.'],
        'fiscal_year.close' => ['Clôturer un exercice', 'M02', 'Rend l’exercice en lecture seule.'],
        'fiscal_year.reopen' => ['Réouvrir un exercice clôturé', 'M02', 'Opération sensible, motivée et journalisée.'],
        'client.view' => ['Consulter les clients', 'M03', null],
        'client.manage' => ['Gérer les clients', 'M03', null],
        'supplier.view' => ['Consulter les fournisseurs', 'M03', null],
        'supplier.manage' => ['Gérer les fournisseurs', 'M03', null],
        'prospect.view' => ['Consulter les prospects', 'M03', 'Fiches commerciales avant conversion en client.'],
        'prospect.manage' => ['Créer et modifier un prospect', 'M03', null],
        'prospect.import' => ['Importer des prospects depuis Excel', 'M03', 'Import avec correspondance explicite des colonnes et mise à jour des doublons.'],
        'prospect.convert' => ['Convertir un prospect', 'M03', 'Crée un client, un projet et un contrat brouillon.'],
        'project.view_all' => ['Consulter tous les projets', 'M12', null],
        'project.view_own' => ['Consulter ses projets', 'M12', 'Limité aux projets dont l’utilisateur est responsable ou membre.'],
        'project.manage' => ['Créer et modifier un projet', 'M12', null],
        'project.validate' => ['Valider le statut contractuel', 'M12', 'Passage au statut « contrat signé ».'],
        'project.close' => ['Clôturer un projet', 'M12', null],
        'contract.view_all' => ['Consulter tous les contrats', 'M15', null],
        'contract.view_own' => ['Consulter ses contrats', 'M15', 'Limité aux contrats dont l’utilisateur est responsable, créateur ou membre du projet.'],
        'contract.manage' => ['Créer et modifier un contrat', 'M15', 'Contrats, avenants, échéances, réceptions et documents.'],
        'contract.validate' => ['Valider, signer ou clôturer un contrat', 'M15', 'Opération sensible et journalisée.'],
        'contract.renew' => ['Décider d’un renouvellement', 'M15', 'Aucun renouvellement n’est automatique.'],
        'contract.bill' => ['Facturer une échéance contractuelle', 'M15', 'Prépare ou constate la facturation d’une échéance.'],
        'contract.notify' => ['Gérer les alertes contractuelles', 'M15', 'Génération, prise en charge et clôture des alertes.'],
        'invoice.view' => ['Consulter les factures', 'M04', null],
        'invoice.manage' => ['Gérer les factures', 'M04', null],
        'invoice.cancel' => ['Annuler une facture', 'M04', 'Opération sensible et journalisée.'],
        'receipt.view' => ['Consulter les encaissements', 'M04', null],
        'receipt.manage' => ['Gérer les encaissements', 'M04', null],
        'expense.view' => ['Consulter les charges', 'M05', null],
        'expense.propose' => ['Proposer une dépense', 'M05', null],
        'expense.manage' => ['Gérer les dépenses et paiements', 'M05', null],
        'expense.approve' => ['Valider ou rejeter une dépense', 'M05', 'Séparation des tâches : l’auteur ne peut pas approuver sa propre dépense.'],
        'marketing.view' => ['Consulter les campagnes et appels d’offres', 'M06', null],
        'marketing.manage' => ['Gérer les campagnes et appels d’offres', 'M06', null],
        'allocation.run' => ['Exécuter une répartition', 'M07', null],
        'asset.view' => ['Consulter les immobilisations', 'M08', null],
        'asset.manage' => ['Gérer les immobilisations', 'M08', null],
        'treasury.view' => ['Consulter la trésorerie', 'M09', null],
        'treasury.manage' => ['Gérer les comptes et mouvements de trésorerie', 'M09', null],
        'partner.view' => ['Consulter les associés', 'M10', 'Données sensibles : capital, avances, distributions.'],
        'partner.manage' => ['Gérer les associés', 'M10', null],
        'partner.validate' => ['Valider apports, avances et remboursements', 'M10', null],
        'annual_result.view' => ['Consulter le résultat annuel', 'M11', null],
        'annual_result.prepare' => ['Préparer le résultat annuel', 'M11', null],
        'annual_result.validate' => ['Valider une clôture de gestion', 'M11', null],
        'distribution.simulate' => ['Simuler une distribution', 'M11', null],
        'distribution.decide' => ['Enregistrer une distribution décidée', 'M11', null],
        'report.view' => ['Consulter les rapports', 'M13', null],
        'report.export' => ['Exporter les rapports', 'M13', 'Exports PDF et XLSX.'],
    ];

    /**
     * Attribution par défaut (docs/03-matrice-permissions.md).
     *
     * @var array<string, array{label: string, description: string, permissions: list<string>}>
     */
    private const ROLES = [
        'ROLE_ADMIN' => [
            'label' => 'Administrateur',
            'description' => 'Comptes, référentiels, règles et journal d’audit. Ne valide pas les opérations financières par défaut.',
            'permissions' => [
                'admin.users', 'admin.referentials', 'admin.allocation_rules', 'admin.audit', 'admin.settings',
                'fiscal_year.reopen',
                'client.view', 'client.manage', 'supplier.view', 'supplier.manage',
                'prospect.view', 'prospect.manage', 'prospect.import', 'prospect.convert',
                'project.view_all', 'project.view_own',
                'contract.view_all',
                'invoice.view', 'receipt.view',
                'expense.view',
                'report.view', 'report.export',
            ],
        ],
        'ROLE_DIRECTION' => [
            'label' => 'Direction générale',
            'description' => 'Lecture globale, validations, clôtures et simulations.',
            'permissions' => [
                'fiscal_year.close',
                'client.view', 'supplier.view',
                'prospect.view',
                'project.view_all', 'project.view_own', 'project.validate', 'project.close',
                'contract.view_all', 'contract.validate', 'contract.renew', 'contract.notify',
                'invoice.view', 'invoice.cancel', 'receipt.view',
                'expense.view', 'expense.approve',
                'marketing.view',
                'allocation.run',
                'asset.view',
                'treasury.view',
                'partner.view', 'partner.manage', 'partner.validate',
                'annual_result.view', 'annual_result.validate',
                'distribution.simulate', 'distribution.decide',
                'report.view', 'report.export',
            ],
        ],
        'ROLE_FINANCE' => [
            'label' => 'Responsable financier',
            'description' => 'Encaissements, décaissements, charges et trésorerie.',
            'permissions' => [
                'fiscal_year.close',
                'client.view', 'supplier.view',
                'prospect.view',
                'project.view_all', 'project.view_own', 'project.close',
                'contract.view_all', 'contract.bill', 'contract.notify',
                'invoice.view', 'invoice.manage', 'invoice.cancel',
                'receipt.view', 'receipt.manage',
                'expense.view', 'expense.propose', 'expense.manage', 'expense.approve',
                'marketing.view',
                'allocation.run',
                'asset.view', 'asset.manage',
                'treasury.view', 'treasury.manage',
                'partner.view', 'partner.manage', 'partner.validate',
                'annual_result.view', 'annual_result.prepare',
                'distribution.simulate',
                'report.view', 'report.export',
            ],
        ],
        'ROLE_COMMERCIAL' => [
            'label' => 'Responsable commercial',
            'description' => 'Tiers, opportunités, appels d’offres, projets et budgets.',
            'permissions' => [
                'client.view', 'client.manage', 'supplier.view', 'supplier.manage',
                'prospect.view', 'prospect.manage', 'prospect.import', 'prospect.convert',
                'project.view_all', 'project.view_own', 'project.manage',
                'contract.view_all', 'contract.view_own', 'contract.manage', 'contract.renew', 'contract.notify',
                'invoice.view', 'receipt.view',
                'expense.view', 'expense.propose',
                'marketing.view', 'marketing.manage',
                'report.view',
            ],
        ],
        'ROLE_PROJECT_MANAGER' => [
            'label' => 'Chef de projet',
            'description' => 'Lecture de ses projets et propositions de dépense.',
            'permissions' => [
                'project.view_own',
                'contract.view_own', 'contract.notify',
                'invoice.view', 'receipt.view',
                'expense.view', 'expense.propose',
                'report.view',
            ],
        ],
    ];

    /** @var array<string, array{label: string, description: string}> */
    private const PROJECT_TYPES = [
        'ETUDE' => ['label' => 'Étude et conseil', 'description' => 'Missions d’étude, d’audit et de conseil.'],
        'FORFAIT' => ['label' => 'Projet au forfait', 'description' => 'Prestation à prix global et forfaitaire.'],
        'REGIE' => ['label' => 'Assistance technique en régie', 'description' => 'Facturation au temps passé.'],
        'MAINTENANCE' => ['label' => 'Maintenance et support', 'description' => 'Contrats récurrents de maintenance.'],
        'FORMATION' => ['label' => 'Formation', 'description' => 'Sessions et parcours de formation.'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ReferenceGenerator $referenceGenerator,
        private readonly \App\Service\InvoiceService $invoiceService,
        private readonly \App\Service\ExpenseService $expenseService,
        private readonly \App\Service\TreasuryService $treasuryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Supprime toutes les données métier avant de recréer le jeu de démonstration.')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe des comptes de démonstration.', self::DEFAULT_PASSWORD);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('reset')) {
            if ($input->isInteractive() && !$io->confirm('Supprimer toutes les données métier existantes ?', false)) {
                $io->warning('Réinitialisation annulée.');

                return Command::SUCCESS;
            }
            $this->reset($io);
        }

        $permissions = $this->seedPermissions();
        $roles = $this->seedRoles($permissions);
        $users = $this->seedUsers($roles, (string) $input->getOption('password'));
        $types = $this->seedProjectTypes();
        $fiscalYears = $this->seedFiscalYears($users);
        [$clients, $suppliers] = $this->seedThirdParties($users);

        $this->seedProjects($users, $clients, $types);
        $this->seedContracts($users, $clients);

        $this->entityManager->flush();
        $this->seedFinance($users, $clients, $suppliers);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $io->success([
            sprintf('%d permissions, %d rôles, %d comptes.', \count($permissions), \count($roles), \count($users)),
            'Connexion : admin / '.$input->getOption('password').' (comptes direction, finance, commercial, chefprojet).',
        ]);

        return Command::SUCCESS;
    }

    private function reset(SymfonyStyle $io): void
    {
        $connection = $this->entityManager->getConnection();
        $io->note('Suppression des données métier existantes…');

        $connection->executeStatement('DELETE FROM audit_logs');

        // Finance : enfants avant parents, les factures et dépenses référencent
        // les clients, fournisseurs, contrats et projets.
        $connection->executeStatement('DELETE FROM payments');
        $connection->executeStatement('DELETE FROM expense_payments');
        $connection->executeStatement('DELETE FROM invoice_lines');
        $connection->executeStatement('DELETE FROM invoices');
        $connection->executeStatement('DELETE FROM expenses');
        $connection->executeStatement('DELETE FROM cash_movements');
        $connection->executeStatement('DELETE FROM partner_transactions');
        $connection->executeStatement('DELETE FROM bank_accounts');
        $connection->executeStatement('DELETE FROM partners');

        // Contrats : la référence circulaire contracts.document_id /
        // contract_documents.contract_id est rompue avant suppression.
        $connection->executeStatement('UPDATE contracts SET document_id = NULL');
        $connection->executeStatement('DELETE FROM contract_notifications');
        $connection->executeStatement('DELETE FROM contract_milestone_histories');
        $connection->executeStatement('DELETE FROM retention_guarantees');
        $connection->executeStatement('DELETE FROM contract_reservations');
        $connection->executeStatement('DELETE FROM contract_receipts');
        $connection->executeStatement('DELETE FROM contract_renewals');
        $connection->executeStatement('DELETE FROM contract_milestones');
        $connection->executeStatement('DELETE FROM maintenance_contracts');
        $connection->executeStatement('DELETE FROM contracts');
        $connection->executeStatement('DELETE FROM contract_documents');

        $connection->executeStatement('DELETE FROM prospects');
        $connection->executeStatement('DELETE FROM project_milestones');
        $connection->executeStatement('DELETE FROM projects');
        $connection->executeStatement('DELETE FROM user_roles');
        $connection->executeStatement('DELETE FROM role_permissions');
        $connection->executeStatement('DELETE FROM users');
        $connection->executeStatement('DELETE FROM roles');
        $connection->executeStatement('DELETE FROM permissions');
        $connection->executeStatement('DELETE FROM clients');
        $connection->executeStatement('DELETE FROM suppliers');
        $connection->executeStatement('DELETE FROM project_types');
        $connection->executeStatement('DELETE FROM fiscal_years');
        $connection->executeStatement('TRUNCATE TABLE reference_sequences');

        $this->entityManager->clear();
    }

    /**
     * @return array<string, Permission>
     */
    private function seedPermissions(): array
    {
        $repository = $this->entityManager->getRepository(Permission::class);
        $permissions = [];

        foreach (self::PERMISSIONS as $code => [$label, $module, $description]) {
            $permission = $repository->findOneBy(['code' => $code]);
            if (null === $permission) {
                $permission = new Permission($code, $label);
                $this->entityManager->persist($permission);
            }
            $permission->setLabel($label)->setModule($module)->setDescription($description);
            $permissions[$code] = $permission;
        }

        $this->entityManager->flush();

        return $permissions;
    }

    /**
     * @param array<string, Permission> $permissions
     *
     * @return array<string, Role>
     */
    private function seedRoles(array $permissions): array
    {
        $repository = $this->entityManager->getRepository(Role::class);
        $roles = [];

        foreach (self::ROLES as $code => $definition) {
            $role = $repository->findOneBy(['code' => $code]);
            if (null === $role) {
                $role = new Role($code, $definition['label']);
                $this->entityManager->persist($role);
            }

            $role->setLabel($definition['label'])
                ->setDescription($definition['description'])
                ->setIsSystem(true);

            foreach ($role->getPermissions()->toArray() as $existing) {
                if (!\in_array($existing->getCode(), $definition['permissions'], true)) {
                    $role->removePermission($existing);
                }
            }

            foreach ($definition['permissions'] as $permissionCode) {
                if (!isset($permissions[$permissionCode])) {
                    throw new \RuntimeException(sprintf('Permission inconnue dans le rôle %s : %s', $code, $permissionCode));
                }
                $role->addPermission($permissions[$permissionCode]);
            }

            $roles[$code] = $role;
        }

        $this->entityManager->flush();

        return $roles;
    }

    /**
     * @param array<string, Role> $roles
     *
     * @return array<string, User>
     */
    private function seedUsers(array $roles, string $password): array
    {
        $repository = $this->entityManager->getRepository(User::class);

        $definitions = [
            'admin' => ['Admin Système', 'admin@one-erp.tn', ['ROLE_ADMIN']],
            'direction' => ['Leïla Trabelsi', 'direction@one-erp.tn', ['ROLE_DIRECTION']],
            'finance' => ['Karim Haddad', 'finance@one-erp.tn', ['ROLE_FINANCE']],
            'commercial' => ['Inès Gharbi', 'commercial@one-erp.tn', ['ROLE_COMMERCIAL']],
            'chefprojet' => ['Nour Ben Salah', 'chefprojet@one-erp.tn', ['ROLE_PROJECT_MANAGER']],
        ];

        $users = [];
        foreach ($definitions as $username => [$fullName, $email, $roleCodes]) {
            $user = $repository->findOneBy(['username' => $username]);
            if (null === $user) {
                $user = new User();
                $user->setUsername($username);
                $this->entityManager->persist($user);
            }

            $user->setFullName($fullName)
                ->setEmail($email)
                ->setIsActive(true);

            foreach ($user->getAssignedRoles()->toArray() as $existingRole) {
                if (!\in_array($existingRole->getCode(), $roleCodes, true)) {
                    $user->removeRole($existingRole);
                }
            }
            foreach ($roleCodes as $roleCode) {
                $user->addRole($roles[$roleCode]);
            }

            if (null === $user->getPassword()) {
                $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
            }

            $users[$username] = $user;
        }

        $this->entityManager->flush();

        return $users;
    }

    /**
     * @return array<string, ProjectType>
     */
    private function seedProjectTypes(): array
    {
        $repository = $this->entityManager->getRepository(ProjectType::class);
        $types = [];
        $position = 0;

        foreach (self::PROJECT_TYPES as $code => $definition) {
            $type = $repository->findOneBy(['code' => $code]);
            if (null === $type) {
                $type = new ProjectType();
                $type->setCode($code);
                $this->entityManager->persist($type);
            }
            $type->setLabel($definition['label'])
                ->setDescription($definition['description'])
                ->setSortOrder($position += 10)
                ->setIsActive(true);

            $types[$code] = $type;
        }

        $this->entityManager->flush();

        return $types;
    }

    /**
     * @param array<string, User> $users
     *
     * @return array<string, FiscalYear>
     */
    private function seedFiscalYears(array $users): array
    {
        $repository = $this->entityManager->getRepository(FiscalYear::class);

        $previous = $repository->findOneBy(['label' => 'Exercice 2025']);
        if (null === $previous) {
            $previous = new FiscalYear();
            $previous->setLabel('Exercice 2025');
            $this->entityManager->persist($previous);
        }
        $previous->setStartDate(new \DateTimeImmutable('2025-01-01'))
            ->setEndDate(new \DateTimeImmutable('2025-12-31'))
            ->setCreatedBy($users['admin']);

        if (FiscalYearStatus::Closed !== $previous->getStatus()) {
            $previous->close($users['direction']);
        }

        $current = $repository->findOneBy(['label' => 'Exercice 2026']);
        if (null === $current) {
            $current = new FiscalYear();
            $current->setLabel('Exercice 2026');
            $this->entityManager->persist($current);
        }
        $current->setStartDate(new \DateTimeImmutable('2026-01-01'))
            ->setEndDate(new \DateTimeImmutable('2026-12-31'))
            ->setStatus(FiscalYearStatus::Open)
            ->setCreatedBy($users['admin']);

        $this->entityManager->flush();

        return ['2025' => $previous, '2026' => $current];
    }

    /**
     * @param array<string, User> $users
     *
     * @return array{0: array<string, Client>, 1: array<string, Supplier>}
     */
    private function seedThirdParties(array $users): array
    {
        $clientRepository = $this->entityManager->getRepository(Client::class);
        $clientDefinitions = [
            'CLI-001' => [
                'name' => 'Banque Atlas',
                'type' => ClientType::Private,
                'email' => 'contact@banque-atlas.tn',
                'phone' => '+216 71 000 001',
                'city' => 'Tunis',
                'contact' => 'Mehdi Jaziri',
                'notes' => 'Client historique — paiement à 45 jours.',
            ],
            'CLI-002' => [
                'name' => 'Groupe Méditerranée Santé',
                'type' => ClientType::Private,
                'email' => 'achats@med-sante.tn',
                'phone' => '+216 73 000 002',
                'city' => 'Sousse',
                'contact' => 'Salma Bouazizi',
                'notes' => null,
            ],
            'CLI-003' => [
                'name' => 'Ministère du Développement Régional',
                'type' => ClientType::Public,
                'email' => null,
                'phone' => '+216 71 000 003',
                'city' => 'Tunis',
                'contact' => 'Direction des systèmes d’information',
                'notes' => 'Marchés publics — délai de paiement réglementaire.',
            ],
            'CLI-004' => [
                'name' => 'Association Horizon Jeunesse',
                'type' => ClientType::Association,
                'email' => 'contact@horizon-jeunesse.tn',
                'phone' => null,
                'city' => 'Sfax',
                'contact' => 'Nadia Kefi',
                'notes' => null,
            ],
        ];

        $clients = [];
        foreach ($clientDefinitions as $code => $definition) {
            $client = $clientRepository->findOneBy(['code' => $code]);
            if (null === $client) {
                $client = new Client();
                $client->setCode($code);
                $this->entityManager->persist($client);
            }
            $client->setName($definition['name'])
                ->setClientType($definition['type'])
                ->setEmail($definition['email'])
                ->setPhone($definition['phone'])
                ->setCity($definition['city'])
                ->setCountry('Tunisie')
                ->setContactName($definition['contact'])
                ->setPaymentTermsDays(ClientType::Public === $definition['type'] ? 60 : 30)
                ->setIsActive(true)
                ->setNotes($definition['notes'])
                ->setCreatedBy($users['commercial']);

            $clients[$code] = $client;
        }

        $supplierRepository = $this->entityManager->getRepository(Supplier::class);
        $supplierDefinitions = [
            'FRN-001' => [
                'name' => 'Bureau Technique Ennasr',
                'category' => 'Prestations d’ingénierie',
                'email' => 'contact@bt-ennasr.tn',
                'city' => 'Ariana',
            ],
            'FRN-002' => [
                'name' => 'Société Informatique Carthage',
                'category' => 'Matériel et licences',
                'email' => 'commercial@si-carthage.tn',
                'city' => 'Tunis',
            ],
            'FRN-003' => [
                'name' => 'Cabinet d’expertise comptable El Amen',
                'category' => 'Services comptables',
                'email' => 'contact@elamen.tn',
                'city' => 'Tunis',
            ],
        ];

        $suppliers = [];
        foreach ($supplierDefinitions as $code => $definition) {
            $supplier = $supplierRepository->findOneBy(['code' => $code]);
            if (null === $supplier) {
                $supplier = new Supplier();
                $supplier->setCode($code);
                $this->entityManager->persist($supplier);
            }
            $supplier->setName($definition['name'])
                ->setServiceCategory($definition['category'])
                ->setEmail($definition['email'])
                ->setCity($definition['city'])
                ->setCountry('Tunisie')
                ->setPaymentTermsDays(30)
                ->setIsActive(true)
                ->setCreatedBy($users['commercial']);

            $suppliers[$code] = $supplier;
        }

        $this->entityManager->flush();

        return [$clients, $suppliers];
    }

    /**
     * @param array<string, User>        $users
     * @param array<string, Client>      $clients
     * @param array<string, ProjectType> $types
     */
    private function seedProjects(array $users, array $clients, array $types): void
    {
        $repository = $this->entityManager->getRepository(Project::class);

        if (0 === $repository->count([])) {
            $definitions = [
                [
                    'name' => 'Refonte du portail client',
                    'client' => 'CLI-001',
                    'type' => 'FORFAIT',
                    'manager' => 'chefprojet',
                    'status' => ProjectStatus::InProgress,
                    'amount' => '42000.000',
                    'budget' => '24000.000',
                    'contract' => 'CTR-2026-014',
                    'signed' => '2026-01-15',
                    'start' => '2026-01-20',
                    'end' => '2026-09-30',
                    'milestones' => [
                        ['Cadrage et spécifications', '2026-02-28', '10500.000', MilestoneStatus::Invoiced],
                        ['Développement du socle', '2026-05-31', '16800.000', MilestoneStatus::Reached],
                        ['Recette et mise en production', '2026-09-15', '14700.000', MilestoneStatus::Planned],
                    ],
                    'notes' => 'Projet pilote de la phase 1.',
                ],
                [
                    'name' => 'Audit organisationnel et schéma directeur',
                    'client' => 'CLI-003',
                    'type' => 'ETUDE',
                    'manager' => 'commercial',
                    'status' => ProjectStatus::Won,
                    'amount' => '40000.000',
                    'budget' => '18000.000',
                    'contract' => 'MP-2026-07',
                    'signed' => '2026-02-05',
                    'start' => '2026-03-01',
                    'end' => '2026-08-31',
                    'milestones' => [
                        ['Diagnostic et rapport intermédiaire', '2026-04-30', '16000.000', MilestoneStatus::Reached],
                        ['Schéma directeur cible', '2026-07-31', '24000.000', MilestoneStatus::Planned],
                    ],
                    'notes' => 'Marché public — réception par lot.',
                ],
                [
                    'name' => 'Application de gestion des rendez-vous',
                    'client' => 'CLI-002',
                    'type' => 'REGIE',
                    'manager' => 'chefprojet',
                    'status' => ProjectStatus::Opportunity,
                    'amount' => '12000.000',
                    'budget' => '0.000',
                    'contract' => null,
                    'signed' => null,
                    'start' => null,
                    'end' => null,
                    'milestones' => [],
                    'notes' => 'Opportunité identifiée — proposition envoyée.',
                ],
                [
                    'name' => 'Maintenance applicative annuelle',
                    'client' => 'CLI-004',
                    'type' => 'MAINTENANCE',
                    'manager' => 'chefprojet',
                    'status' => ProjectStatus::OnHold,
                    'amount' => '8500.000',
                    'budget' => '4200.000',
                    'contract' => 'CTR-2026-021',
                    'signed' => '2026-03-12',
                    'start' => '2026-04-01',
                    'end' => '2027-03-31',
                    'milestones' => [
                        ['Forfait trimestriel T3 2026', '2026-09-30', '2125.000', MilestoneStatus::Planned],
                    ],
                    'notes' => 'Suspendu à la demande du client.',
                ],
                [
                    'name' => 'Formation des équipes métier',
                    'client' => 'CLI-001',
                    'type' => 'FORMATION',
                    'manager' => 'commercial',
                    'status' => ProjectStatus::Delivered,
                    'amount' => '6500.000',
                    'budget' => '3100.000',
                    'contract' => 'CTR-2025-118',
                    'signed' => '2025-10-02',
                    'start' => '2025-10-15',
                    'end' => '2025-12-20',
                    'milestones' => [
                        ['Session intra-entreprise', '2025-12-20', '6500.000', MilestoneStatus::Invoiced],
                    ],
                    'notes' => 'Projet livré sur l’exercice 2025.',
                ],
            ];

            foreach ($definitions as $index => $definition) {
                $project = new Project();
                $project->setCode($this->referenceGenerator->next(ReferenceGenerator::MODULE_PROJECT))
                    ->setName($definition['name'])
                    ->setClient($clients[$definition['client']])
                    ->setProjectType($types[$definition['type']])
                    ->setManager($users[$definition['manager']])
                    ->setStatus($definition['status'])
                    ->setContractAmountHt($definition['amount'])
                    ->setVatRate('19.0000')
                    ->setBudgetAmount($definition['budget'])
                    ->setContractReference($definition['contract'])
                    ->setSignedOn($definition['signed'] ? new \DateTimeImmutable($definition['signed']) : null)
                    ->setStartDate($definition['start'] ? new \DateTimeImmutable($definition['start']) : null)
                    ->setEndDate($definition['end'] ? new \DateTimeImmutable($definition['end']) : null)
                    ->setNotes($definition['notes'])
                    ->setCreatedBy($users['commercial']);

                if (\in_array($definition['status'], [ProjectStatus::Won, ProjectStatus::InProgress, ProjectStatus::OnHold], true)) {
                    $project->addMember($users['finance']);
                }

                foreach ($definition['milestones'] as $position => [$label, $date, $amount, $status]) {
                    $milestone = new ProjectMilestone();
                    $milestone->setLabel($label)
                        ->setPlannedDate(new \DateTimeImmutable($date))
                        ->setAmountHt($amount)
                        ->setStatus($status)
                        ->setPosition($position + 1);
                    $project->addMilestone($milestone);
                }

                $project->recomputeTotals();
                $this->entityManager->persist($project);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Contrats, échéances et maintenance de démonstration (M15).
     *
     * @param array<string, User>   $users
     * @param array<string, Client> $clients
     */
    private function seedContracts(array $users, array $clients): void
    {
        $contractRepository = $this->entityManager->getRepository(Contract::class);
        if (0 !== $contractRepository->count([])) {
            return;
        }

        $projectRepository = $this->entityManager->getRepository(Project::class);
        $projectPortal = $projectRepository->findOneBy(['name' => 'Refonte du portail client']);
        $projectAudit = $projectRepository->findOneBy(['name' => 'Audit organisationnel et schéma directeur']);
        $projectMaintenance = $projectRepository->findOneBy(['name' => 'Maintenance applicative annuelle']);

        $definitions = [
            [
                'title' => 'Refonte du portail client — contrat forfaitaire',
                'type' => ContractType::Private,
                'client' => 'CLI-001',
                'project' => $projectPortal,
                'market' => null,
                'signature' => '2026-01-15',
                'start' => '2026-01-20',
                'end' => '2026-12-31',
                'amount' => '42000.000',
                'status' => ContractStatus::InProgress,
                'responsible' => 'chefprojet',
                'payment_terms' => '30 % à la commande, 40 % à mi-parcours, solde à la réception.',
                'renewal' => null,
                'notice' => 60,
                'warranty' => 365,
                'notes' => 'Contrat pilote de démonstration.',
                'milestones' => [
                    ['initial_deposit', 'Acompte de démarrage', '2026-02-15', '2026-02-14', '8400.000', CompletionStatus::Completed, ContractBillingStatus::NotBillable],
                    ['progress', 'Situation de travaux à mi-parcours', '2026-07-31', '2026-07-28', '16800.000', CompletionStatus::Completed, ContractBillingStatus::ReadyToInvoice],
                    ['lot_delivery', 'Livraison finale et mise en production', '2026-12-15', null, '16800.000', CompletionStatus::Upcoming, ContractBillingStatus::NotBillable],
                ],
            ],
            [
                'title' => 'Audit organisationnel et schéma directeur',
                'type' => ContractType::PublicProcurement,
                'client' => 'CLI-003',
                'project' => $projectAudit,
                'market' => 'MP-2026-07',
                'signature' => '2026-02-05',
                'start' => '2026-03-01',
                'end' => '2026-08-31',
                'amount' => '40000.000',
                'status' => ContractStatus::InProgress,
                'responsible' => 'commercial',
                'payment_terms' => 'Paiement à 60 jours après service fait.',
                'renewal' => null,
                'notice' => 90,
                'warranty' => 180,
                'notes' => 'Marché public — réception par lot.',
                'milestones' => [
                    ['progress', 'Rapport intermédiaire', '2026-04-30', '2026-04-29', '16000.000', CompletionStatus::Completed, ContractBillingStatus::ReadyToInvoice],
                    ['lot_delivery', 'Schéma directeur cible', '2026-07-31', null, '24000.000', CompletionStatus::Upcoming, ContractBillingStatus::NotBillable],
                ],
            ],
            [
                'title' => 'Maintenance applicative annuelle',
                'type' => ContractType::Maintenance,
                'client' => 'CLI-004',
                'project' => $projectMaintenance,
                'market' => null,
                'signature' => '2026-03-12',
                'start' => '2026-04-01',
                'end' => '2027-03-31',
                'amount' => '8500.000',
                'status' => ContractStatus::ActiveMaintenance,
                'responsible' => 'chefprojet',
                'payment_terms' => 'Facturation trimestrielle à échoir.',
                'renewal' => 'Renouvellement annuel explicite, préavis de 60 jours.',
                'notice' => 60,
                'warranty' => 0,
                'notes' => 'Contrat de maintenance récurrent.',
                'milestones' => [
                    ['maintenance_period', 'Forfait trimestriel T3 2026', '2026-09-30', '2026-09-30', '2125.000', CompletionStatus::Completed, ContractBillingStatus::ReadyToInvoice],
                    ['maintenance_period', 'Forfait trimestriel T4 2026', '2026-12-31', null, '2125.000', CompletionStatus::Upcoming, ContractBillingStatus::NotBillable],
                ],
                'maintenance' => true,
            ],
        ];

        foreach ($definitions as $definition) {
            $contract = new Contract();
            $contract->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_CONTRACT))
                ->setTitle($definition['title'])
                ->setContractType($definition['type'])
                ->setClient($clients[$definition['client']])
                ->setProject($definition['project'])
                ->setMarketReference($definition['market'])
                ->setSignatureDate(new \DateTimeImmutable($definition['signature']))
                ->setEffectiveStartDate(new \DateTimeImmutable($definition['start']))
                ->setExecutionStartDate(new \DateTimeImmutable($definition['start']))
                ->setEffectiveEndDate(new \DateTimeImmutable($definition['end']))
                ->setAmountHt($definition['amount'])
                ->setVatRate('19.0000')
                ->setCurrencyCode('TND')
                ->setResponsible($users[$definition['responsible']])
                ->setPaymentTerms($definition['payment_terms'])
                ->setRenewalTerms($definition['renewal'])
                ->setNoticePeriodDays($definition['notice'])
                ->setRenewalMode(RenewalMode::Manual)
                ->setWarrantyDurationDays($definition['warranty'])
                ->setStatus($definition['status'])
                ->setNotes($definition['notes'])
                ->setCreatedBy($users['commercial']);

            foreach ($definition['milestones'] as [$type, $label, $planned, $actual, $amount, $completion, $billing]) {
                $milestone = new ContractMilestone();
                $milestone->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_CONTRACT_MILESTONE))
                    ->setContract($contract)
                    ->setProject($contract->getProject())
                    ->setMilestoneType(MilestoneType::from($type))
                    ->setLabel($label)
                    ->setPlannedDate(new \DateTimeImmutable($planned))
                    ->setActualDate(null !== $actual ? new \DateTimeImmutable($actual) : null)
                    ->setAmountHt($amount)
                    ->setVatRate('19.0000')
                    ->setCompletionStatus($completion)
                    ->setBillingStatus($billing)
                    ->setResponsible($users[$definition['responsible']]);
                $contract->addMilestone($milestone);
            }

            $this->entityManager->persist($contract);

            if (true === ($definition['maintenance'] ?? false)) {
                $maintenance = new MaintenanceContract();
                $maintenance->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_MAINTENANCE))
                    ->setContract($contract)
                    ->setMaintenanceStartDate(new \DateTimeImmutable('2026-04-01'))
                    ->setMaintenanceEndDate(new \DateTimeImmutable('2027-03-31'))
                    ->setDurationMonths(12)
                    ->setBillingFrequency(BillingFrequency::Quarterly)
                    ->setPeriodicAmountHt('2125.000')
                    ->setAnnualAmountHt('8500.000')
                    ->setVatRate('19.0000')
                    ->setPriceRevisionTerms('Révision annuelle selon indice INSEE.')
                    ->setRenewalNoticeDays(60)
                    ->setRenewalMode(RenewalMode::Manual)
                    ->setRenewalStatus(MaintenanceRenewalStatus::ToPrepare)
                    ->setCommercialResponsible($users['commercial'])
                    ->setTechnicalResponsible($users['chefprojet'])
                    ->setNotes('Maintenance préventive et corrective.');
                $this->entityManager->persist($maintenance);
            }
        }
    }

    /**
     * Données de facturation, charges, trésorerie et associés.
     *
     * @param array<string, User>     $users
     * @param array<string, Client>   $clients
     * @param array<string, Supplier> $suppliers
     */
    private function seedFinance(array $users, array $clients, array $suppliers): void
    {
        if (0 !== $this->entityManager->getRepository(BankAccount::class)->count([])) {
            return;
        }

        $bank = new BankAccount();
        $bank->setName('Compte courant BIAT')
            ->setBankName('BIAT')
            ->setAccountNumber('120 456 789 012')
            ->setIban('TN5910006035181204567890')
            ->setCurrencyCode('TND')
            ->setOpeningBalance('150000.000')
            ->setOpeningDate(new \DateTimeImmutable('2026-01-01'))
            ->setIsActive(true)
            ->setCreatedBy($users['finance']);
        $this->entityManager->persist($bank);

        $cash = new BankAccount();
        $cash->setName('Caisse principale')
            ->setBankName('Caisse interne')
            ->setCurrencyCode('TND')
            ->setOpeningBalance('2500.000')
            ->setOpeningDate(new \DateTimeImmutable('2026-01-01'))
            ->setIsActive(true)
            ->setCreatedBy($users['finance']);
        $this->entityManager->persist($cash);

        $partnerDefinitions = [
            ['ASS-001', 'Slim Ben Ammar', '70.0000', PartnerType::Individual, 'slim@one-erp.tn'],
            ['ASS-002', 'Société Carthage Invest', '20.0000', PartnerType::Company, 'contact@carthage-invest.tn'],
            ['ASS-003', 'Leïla Trabelsi', '10.0000', PartnerType::Individual, 'direction@one-erp.tn'],
        ];

        $partners = [];
        foreach ($partnerDefinitions as [$code, $name, $share, $type, $email]) {
            $partner = new Partner();
            $partner->setCode($code)->setName($name)->setPartnerType($type)
                ->setOwnershipPercent($share)->setEmail($email)->setIsActive(true)
                ->setCreatedBy($users['direction']);
            $this->entityManager->persist($partner);
            $partners[] = $partner;
        }

        $capital = new PartnerTransaction();
        $capital->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_PARTNER))
            ->setPartner($partners[0])
            ->setTransactionType(PartnerTransactionType::CapitalContribution)
            ->setStatus(PartnerTransactionStatus::Paid)
            ->setTransactionDate(new \DateTimeImmutable('2026-01-10'))
            ->setAmount('100000.000')
            ->setCurrencyCode('TND')
            ->setLabel('Apport initial en capital')
            ->setBankAccount($bank)
            ->setCreatedBy($users['direction']);
        $this->entityManager->persist($capital);
        $partners[0]->addTransaction($capital);

        $advance = new PartnerTransaction();
        $advance->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_PARTNER))
            ->setPartner($partners[1])
            ->setTransactionType(PartnerTransactionType::CurrentAccountContribution)
            ->setStatus(PartnerTransactionStatus::Planned)
            ->setTransactionDate(new \DateTimeImmutable('2026-06-15'))
            ->setAmount('25000.000')
            ->setCurrencyCode('TND')
            ->setLabel('Avance en compte courant')
            ->setBankAccount($bank)
            ->setCreatedBy($users['direction']);
        $advance->validate($users['finance'], new \DateTimeImmutable('2026-06-16'));
        $this->entityManager->persist($advance);
        $partners[1]->addTransaction($advance);

        $dividend = new PartnerTransaction();
        $dividend->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_PARTNER))
            ->setPartner($partners[2])
            ->setTransactionType(PartnerTransactionType::Dividend)
            ->setStatus(PartnerTransactionStatus::Planned)
            ->setTransactionDate(new \DateTimeImmutable('2026-12-31'))
            ->setAmount('8000.000')
            ->setCurrencyCode('TND')
            ->setLabel('Dividende à distribuer')
            ->setCreatedBy($users['direction']);
        $this->entityManager->persist($dividend);
        $partners[2]->addTransaction($dividend);

        $contract = $this->entityManager->getRepository(Contract::class)
            ->findOneBy(['title' => 'Refonte du portail client — contrat forfaitaire']);
        $project = $contract?->getProject();
        $milestones = null !== $contract ? $contract->getMilestones()->toArray() : [];
        $milestone = $milestones[0] ?? null;

        if (null !== $contract && null !== $milestone) {
            $invoice = $this->invoiceService->createDraft(
                $clients['CLI-001'],
                $project,
                $contract,
                $milestone,
                $users['finance'],
                new \DateTimeImmutable('2026-02-20'),
            );
            $invoice->getLines()->first()->setDesignation('Acompte de démarrage — refonte du portail client');
            $this->entityManager->flush();
            $this->invoiceService->validate($invoice, $users['finance']);
            $this->entityManager->flush();
            $this->invoiceService->registerPayment(
                $invoice,
                '5000.000',
                new \DateTimeImmutable('2026-03-05'),
                PaymentMethod::BankTransfer,
                $bank,
                'VIR-2026-0305',
                'Acompte reçu',
                $users['finance'],
            );
            $this->entityManager->flush();
        }

        $readyContract = $this->entityManager->getRepository(Contract::class)
            ->findOneBy(['title' => 'Audit organisationnel et schéma directeur']);
        $readyMilestone = null !== $readyContract ? $readyContract->getMilestones()->first() : null;
        if (null !== $readyContract && false !== $readyMilestone) {
            $draft = $this->invoiceService->createDraft(
                $clients['CLI-003'],
                $readyContract->getProject(),
                $readyContract,
                $readyMilestone,
                $users['finance'],
                new \DateTimeImmutable('2026-09-05'),
            );
            $draft->setNotes('Brouillon à valider après réception du bon de commande.');
            $this->entityManager->persist($draft);
            $this->entityManager->flush();
        }

        $expense = new Expense();
        $expense->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_EXPENSE))
            ->setCategory(ExpenseCategory::Subcontracting)
            ->setStatus(ExpenseStatus::Submitted)
            ->setLabel('Sous-traitance développement module reporting')
            ->setSupplier($suppliers['FRN-001'])
            ->setProject($project)
            ->setContract($contract)
            ->setExpenseDate(new \DateTimeImmutable('2026-09-12'))
            ->setDueDate(new \DateTimeImmutable('2026-10-12'))
            ->setAmountHt('12000.000')
            ->setVatRate('19.0000')
            ->setCurrencyCode('TND')
            ->setNotes('Bon de commande et livrable transmis.')
            ->setCreatedBy($users['chefprojet']);
        $expense->recomputeTotals();
        $this->entityManager->persist($expense);

        $expense2 = new Expense();
        $expense2->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_EXPENSE))
            ->setCategory(ExpenseCategory::Equipment)
            ->setStatus(ExpenseStatus::Submitted)
            ->setLabel('Licences outils de développement')
            ->setSupplier($suppliers['FRN-002'])
            ->setExpenseDate(new \DateTimeImmutable('2026-08-01'))
            ->setDueDate(new \DateTimeImmutable('2026-08-31'))
            ->setAmountHt('3200.000')
            ->setVatRate('19.0000')
            ->setCurrencyCode('TND')
            ->setNotes('Renouvellement annuel.')
            ->setCreatedBy($users['finance']);
        $expense2->recomputeTotals();
        $expense2->approve($users['direction']);
        $this->entityManager->persist($expense2);
        $this->entityManager->flush();

        $this->expenseService->registerPayment(
            $expense2,
            '1500.000',
            new \DateTimeImmutable('2026-08-20'),
            PaymentMethod::BankTransfer,
            $bank,
            'VIR-2026-0820',
            'Paiement partiel',
            $users['finance'],
        );
        $this->entityManager->flush();

        $this->treasuryService->record(
            $cash,
            \App\Enum\TreasuryDirection::Inflow,
            \App\Enum\TreasuryMovementType::Other,
            '500.000',
            'Alimentation de la caisse',
            $users['finance'],
            null,
            null,
            new \DateTimeImmutable('2026-09-01'),
            null,
        );
        $this->entityManager->flush();
    }
}
