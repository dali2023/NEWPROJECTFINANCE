<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\FiscalYear;
use App\Enum\FiscalYearStatus;
use App\Tests\Support\DatabaseTestCase;

/**
 * Clôture et réouverture d'un exercice (§4.1, §5.4, REC-014).
 */
final class FiscalYearTest extends DatabaseTestCase
{
    public function testCloseThenReopenWithMandatoryReasonIsAudited(): void
    {
        $user = $this->createUser('ROLE_ADMIN');

        $fiscalYear = new FiscalYear();
        $fiscalYear->setLabel('Exercice 2027')
            ->setStartDate(new \DateTimeImmutable('2027-01-01'))
            ->setEndDate(new \DateTimeImmutable('2027-12-31'))
            ->setCreatedBy($user);
        $this->entityManager->persist($fiscalYear);
        $this->entityManager->flush();

        $this->client->loginUser($user);

        // Clôture.
        $this->client->request('POST', '/admin/exercices/'.$fiscalYear->getIdString().'/cloturer', [
            '_token' => $this->formToken('/admin/exercices/'.$fiscalYear->getIdString().'/cloturer'),
        ]);
        self::assertResponseRedirects('/admin/exercices');

        $this->entityManager->clear();
        $fiscalYear = $this->entityManager->find(FiscalYear::class, $fiscalYear->getId());
        self::assertSame(FiscalYearStatus::Closed, $fiscalYear->getStatus());
        self::assertNotNull($fiscalYear->getClosedAt());

        $closeLog = $this->entityManager->getRepository(AuditLog::class)->findOneBy([
            'action' => 'close',
            'entityId' => $fiscalYear->getIdString(),
        ]);
        self::assertNotNull($closeLog, 'La clôture doit être journalisée.');
        self::assertSame('FiscalYear', $closeLog->getEntity());
        self::assertSame('success', $closeLog->getResult());

        // Réouverture refusée sans motif suffisant.
        $this->client->request('POST', '/admin/exercices/'.$fiscalYear->getIdString().'/reouvrir', [
            '_token' => $this->formToken('/admin/exercices/'.$fiscalYear->getIdString().'/reouvrir'),
            'motif' => 'court',
        ]);
        self::assertResponseRedirects('/admin/exercices');

        $this->entityManager->clear();
        self::assertSame(
            FiscalYearStatus::Closed,
            $this->entityManager->find(FiscalYear::class, $fiscalYear->getId())->getStatus(),
            'Un motif de moins de 10 caractères ne doit pas réouvrir l’exercice.',
        );

        // Réouverture motivée.
        $reason = 'Correction d’une écriture oubliée sur le dernier trimestre.';
        $this->client->request('POST', '/admin/exercices/'.$fiscalYear->getIdString().'/reouvrir', [
            '_token' => $this->formToken('/admin/exercices/'.$fiscalYear->getIdString().'/reouvrir'),
            'motif' => $reason,
        ]);
        self::assertResponseRedirects('/admin/exercices');

        $this->entityManager->clear();
        self::assertSame(
            FiscalYearStatus::Open,
            $this->entityManager->find(FiscalYear::class, $fiscalYear->getId())->getStatus(),
        );

        $reopenLog = $this->entityManager->getRepository(AuditLog::class)->findOneBy([
            'action' => 'reopen',
            'entityId' => $fiscalYear->getIdString(),
        ]);
        self::assertNotNull($reopenLog, 'La réouverture doit être journalisée avec son motif.');
        self::assertStringContainsString($reason, (string) $reopenLog->getMessage());
    }

    private function formToken(string $action): string
    {
        $crawler = $this->client->request('GET', '/admin/exercices');
        self::assertResponseIsSuccessful();

        $token = $crawler
            ->filter(sprintf('form[action="%s"] input[name="_token"]', $action))
            ->attr('value');

        self::assertNotEmpty($token, sprintf('Jeton CSRF introuvable pour %s.', $action));

        return $token;
    }
}
