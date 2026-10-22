<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Client;
use App\Entity\FiscalYear;
use App\Entity\Permission;
use App\Entity\Project;
use App\Entity\ProjectMilestone;
use App\Entity\ProjectType;
use App\Entity\Role;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\ClientType;
use App\Enum\FiscalYearStatus;
use App\Enum\MilestoneStatus;
use App\Enum\ProjectStatus;
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
        'fiscal_year.close' => ['Clôturer un exercice', 'M02', 'Rend l’exercice en lecture seule.'],
        'fiscal_year.reopen' => ['Réouvrir un exercice clôturé', 'M02', 'Opération sensible, motivée et journalisée.'],
        'client.view' => ['Consulter les clients', 'M03', null],
        'client.manage' => ['Gérer les clients', 'M03', null],
        'supplier.view' => ['Consulter les fournisseurs', 'M03', null],
        'supplier.manage' => ['Gérer les fournisseurs', 'M03', null],
        'project.view_all' => ['Consulter tous les projets', 'M12', null],
        'project.view_own' => ['Consulter ses projets', 'M12', 'Limité aux projets dont l’utilisateur est responsable ou membre.'],
        'project.manage' => ['Créer et modifier un projet', 'M12', null],
        'project.validate' => ['Valider le statut contractuel', 'M12', 'Passage au statut « contrat signé ».'],
        'project.close' => ['Clôturer un projet', 'M12', null],
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
                'admin.users', 'admin.referentials', 'admin.allocation_rules', 'admin.audit',
                'fiscal_year.reopen',
                'client.view', 'client.manage', 'supplier.view', 'supplier.manage',
                'project.view_all', 'project.view_own',
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
                'project.view_all', 'project.view_own', 'project.validate', 'project.close',
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
                'project.view_all', 'project.view_own', 'project.close',
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
                'project.view_all', 'project.view_own', 'project.manage',
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
        [$clients] = $this->seedThirdParties($users);

        $this->seedProjects($users, $clients, $types);

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
}
