<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Client;
use App\Entity\Permission;
use App\Entity\Project;
use App\Entity\ProjectType;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\ClientType;
use App\Enum\ProjectStatus;
use App\Tests\Support\DatabaseTestCase;

/**
 * Parcours de création d'un projet (M03) et garde-fou de validation
 * contractuelle (§8.1, REC-015).
 */
final class ProjectCreationTest extends DatabaseTestCase
{
    public function testAdminCanCreateProjectWithExactTotals(): void
    {
        [$user, $clientEntity, $type] = $this->createFixtures('ROLE_ADMIN');

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/projets/nouveau');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer le projet')->form([
            'project_form[name]' => 'Projet de test fonctionnel',
            'project_form[client]' => $clientEntity->getIdString(),
            'project_form[projectType]' => $type->getIdString(),
            'project_form[status]' => ProjectStatus::InProgress->value,
            'project_form[contractAmountHt]' => '40000.000',
            'project_form[vatRate]' => '19',
            'project_form[budgetAmount]' => '18000.000',
        ]);

        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $project = $this->entityManager->getRepository(Project::class)->findOneBy(['name' => 'Projet de test fonctionnel']);

        self::assertNotNull($project);
        self::assertMatchesRegularExpression('/^PRJ-\d{4}-\d{4}$/', $project->getCode());
        self::assertSame('40000.000', $project->getContractAmountHt());
        self::assertSame('19.0000', $project->getVatRate());
        self::assertSame('7600.000', $project->getVatAmount());
        self::assertSame('47600.000', $project->getContractAmountTtc());
        self::assertSame('18000.000', $project->getBudgetAmount());
        self::assertSame(ProjectStatus::InProgress, $project->getStatus());
    }

    public function testContractualStatusRequiresValidationPermission(): void
    {
        [$user, $clientEntity, $type] = $this->createFixtures('ROLE_TEST_COMMERCIAL', withManageOnly: true);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/projets/nouveau');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer le projet')->form([
            'project_form[name]' => 'Projet non validé',
            'project_form[client]' => $clientEntity->getIdString(),
            'project_form[projectType]' => $type->getIdString(),
            'project_form[status]' => ProjectStatus::Won->value,
            'project_form[contractAmountHt]' => '1000.000',
            'project_form[vatRate]' => '19',
            'project_form[budgetAmount]' => '500.000',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Seul un utilisateur habilité peut valider le statut contractuel');

        $this->entityManager->clear();
        self::assertNull(
            $this->entityManager->getRepository(Project::class)->findOneBy(['name' => 'Projet non validé']),
            'Le projet ne doit pas être enregistré lorsque la validation contractuelle est refusée.',
        );
    }

    /**
     * @return array{0: User, 1: Client, 2: ProjectType}
     */
    private function createFixtures(string $roleCode, bool $withManageOnly = false): array
    {
        $suffix = substr(uniqid('', true), -8);

        if ($withManageOnly) {
            $permission = new Permission('project.manage', 'Créer et modifier un projet');
            $this->entityManager->persist($permission);

            $role = new Role($roleCode, 'Commercial de test');
            $role->addPermission($permission);
        } else {
            $role = new Role($roleCode, 'Administrateur de test');
        }
        $this->entityManager->persist($role);

        $user = new User();
        $user->setUsername('creator_'.$suffix)
            ->setFullName('Créateur '.$suffix)
            ->setEmail('creator_'.$suffix.'@one-erp.tn')
            ->setPasswordHash('not-used-in-this-test')
            ->setIsActive(true)
            ->addRole($role);
        $this->entityManager->persist($user);

        $clientEntity = new Client();
        $clientEntity->setCode('CLI-T'.$suffix)
            ->setName('Client de test '.$suffix)
            ->setClientType(ClientType::Private)
            ->setIsActive(true);
        $this->entityManager->persist($clientEntity);

        $type = new ProjectType();
        $type->setCode('TEST_'.$suffix)
            ->setLabel('Type de test '.$suffix)
            ->setIsActive(true);
        $this->entityManager->persist($type);

        $this->entityManager->flush();

        return [$user, $clientEntity, $type];
    }
}
