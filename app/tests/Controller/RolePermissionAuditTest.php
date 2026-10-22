<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Permission;
use App\Tests\Support\DatabaseTestCase;

/**
 * Toute modification de la matrice des permissions est journalisée (M14, §3.6).
 */
final class RolePermissionAuditTest extends DatabaseTestCase
{
    public function testPermissionChangeIsAudited(): void
    {
        $admin = $this->createUser('ROLE_ADMIN');
        $target = $this->createUser('ROLE_TEST_AUDIT');

        $permission = new Permission('report.export', 'Exporter les rapports');
        $this->entityManager->persist($permission);
        $this->entityManager->flush();

        $role = $target->getAssignedRoles()->first();
        self::assertNotFalse($role);

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/roles/'.$role->getIdString());
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer les permissions')->form();
        $values = $form->getPhpValues();
        $values[$form->getName()]['permissions'] = [$permission->getIdString()];

        $this->client->request('POST', '/admin/roles/'.$role->getIdString(), $values);
        self::assertResponseRedirects('/admin/roles');

        $this->entityManager->clear();
        $log = $this->entityManager->getRepository(AuditLog::class)->findOneBy([
            'action' => 'permission_change',
            'entityId' => $role->getIdString(),
        ]);

        self::assertNotNull($log, 'La modification des permissions doit être journalisée.');
        self::assertSame('Role', $log->getEntity());
        self::assertSame('success', $log->getResult());
        self::assertContains($permission->getCode(), $log->getAfterData()['permissions']);
        self::assertNotContains($permission->getCode(), $log->getBeforeData()['permissions']);
    }
}
