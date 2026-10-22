<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\AuditLog;
use App\Entity\Role;
use App\Entity\User;
use App\Tests\Support\DatabaseTestCase;

/**
 * Test F — un accès refusé est journalisé (§3.6, REC-018).
 */
final class AccessDeniedAuditTest extends DatabaseTestCase
{
    public function testDeniedAccessIsWrittenToAuditLog(): void
    {
        $role = new Role('ROLE_TEST_READER', 'Lecteur de test');
        $role->setDescription('Rôle sans permission d’administration.');
        $this->entityManager->persist($role);

        $username = 'reader_'.substr(uniqid('', true), -8);

        $user = new User();
        $user->setUsername($username)
            ->setFullName('Lecteur Test')
            ->setEmail($username.'@one-erp.tn')
            ->setPasswordHash('not-used-in-this-test')
            ->setIsActive(true)
            ->addRole($role);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/utilisateurs');

        self::assertResponseStatusCodeSame(403);

        $this->entityManager->clear();
        $log = $this->entityManager->getRepository(AuditLog::class)->findOneBy(
            ['action' => 'deny', 'username' => $username],
            ['occurredAt' => 'DESC'],
        );

        self::assertNotNull($log, 'Un refus d’accès doit produire une ligne d’audit.');
        self::assertSame('denied', $log->getResult());
        self::assertSame('Route', $log->getEntity());
        self::assertStringContainsString('/admin/utilisateurs', (string) $log->getMessage());
        self::assertSame($username, $log->getUsername());
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/projets');

        self::assertResponseRedirects('/login');
    }
}
