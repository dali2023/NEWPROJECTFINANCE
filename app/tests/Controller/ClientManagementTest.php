<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Client;
use App\Enum\ClientType;
use App\Tests\Support\DatabaseTestCase;

/**
 * REC-001 — création et désactivation d'un client.
 */
final class ClientManagementTest extends DatabaseTestCase
{
    public function testCreateClientThenDeactivateIt(): void
    {
        $user = $this->createUser('ROLE_ADMIN');
        $this->client->loginUser($user);

        $code = 'CLI-T'.substr(uniqid('', true), -6);

        $crawler = $this->client->request('GET', '/clients/nouveau');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer le client')->form([
            'client_form[code]' => $code,
            'client_form[name]' => 'Client créé par test',
            'client_form[clientType]' => ClientType::Private->value,
            'client_form[contactName]' => 'Contact Test',
            'client_form[email]' => strtolower($code).'@example.tn',
            'client_form[city]' => 'Tunis',
            'client_form[country]' => 'Tunisie',
            'client_form[paymentTermsDays]' => '30',
        ]);

        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $client = $this->entityManager->getRepository(Client::class)->findOneBy(['code' => $code]);
        self::assertNotNull($client);
        self::assertSame('Client créé par test', $client->getName());
        self::assertTrue($client->isActive());

        $show = $this->client->request('GET', '/clients/'.$client->getIdString());
        self::assertResponseIsSuccessful();

        $toggleForm = $show->filter('form[action$="/statut"]')->form();
        $this->client->submit($toggleForm);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $client = $this->entityManager->getRepository(Client::class)->findOneBy(['code' => $code]);
        self::assertFalse($client->isActive(), 'La désactivation doit être immédiate.');

        $log = $this->entityManager->getRepository(AuditLog::class)->findOneBy([
            'action' => 'update',
            'entity' => 'Client',
            'entityId' => $client->getIdString(),
        ]);
        self::assertNotNull($log, 'La désactivation doit être journalisée.');
        self::assertStringContainsString('Désactivation', (string) $log->getMessage());
    }
}
