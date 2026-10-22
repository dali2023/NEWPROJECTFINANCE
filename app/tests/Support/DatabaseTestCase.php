<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\User;
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
            $permission = new Permission($code, 'Permission de test '.$code);
            $this->entityManager->persist($permission);
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
}
