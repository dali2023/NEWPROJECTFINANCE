<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\AuditLog;
use App\Entity\Client;
use App\Entity\Project;
use App\Entity\ProjectType;
use App\Enum\ClientType;
use App\Enum\ProjectStatus;
use App\Tests\Support\DatabaseTestCase;

/**
 * REC-015 — un utilisateur ne peut pas consulter un projet dont il n'est ni
 * responsable ni membre. Le refus est journalisé (§3.6).
 */
final class ProjectAccessTest extends DatabaseTestCase
{
    public function testUserCannotViewProjectOutsideHisScope(): void
    {
        $owner = $this->createUser('ROLE_TEST_OWNER');
        $reader = $this->createUser('ROLE_TEST_READER', ['project.view_own']);

        $client = new Client();
        $client->setCode('CLI-P'.substr(uniqid('', true), -4))
            ->setName('Client du projet privé')
            ->setClientType(ClientType::Private)
            ->setIsActive(true);
        $this->entityManager->persist($client);

        $type = new ProjectType();
        $type->setCode('PT'.strtoupper(bin2hex(random_bytes(2))))
            ->setLabel('Type du projet privé')
            ->setIsActive(true);
        $this->entityManager->persist($type);

        $project = new Project();
        $project->setCode('PRJ-TEST-'.substr(uniqid('', true), -4))
            ->setName('Projet hors périmètre')
            ->setClient($client)
            ->setProjectType($type)
            ->setManager($owner)
            ->setCreatedBy($owner)
            ->setStatus(ProjectStatus::InProgress)
            ->setContractAmountHt('1000.000')
            ->setVatRate('19.0000');
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        $this->client->loginUser($reader);
        $this->client->request('GET', '/projets/'.$project->getIdString());

        self::assertResponseStatusCodeSame(403);

        $this->entityManager->clear();
        $log = $this->entityManager->getRepository(AuditLog::class)->findOneBy(
            ['action' => 'deny', 'username' => $reader->getUsername()],
            ['occurredAt' => 'DESC'],
        );

        self::assertNotNull($log);
        self::assertSame('denied', $log->getResult());
        self::assertStringContainsString('/projets/'.$project->getIdString(), (string) $log->getMessage());
    }
}
