<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Entity\ContractMilestone;
use App\Entity\ContractReceipt;
use App\Entity\ContractReservation;
use App\Entity\MaintenanceContract;
use App\Entity\RetentionGuarantee;
use App\Enum\BillingFrequency;
use App\Enum\CompletionStatus;
use App\Enum\ContractBillingStatus;
use App\Enum\ContractStatus;
use App\Enum\ReceiptType;
use App\Enum\ReservationStatus;
use App\Enum\RetentionStatus;
use App\Service\ContractBillingService;
use App\Service\ContractNotificationService;
use App\Service\InvoiceService;
use App\Service\MaintenanceScheduleService;
use App\Tests\Support\DatabaseTestCase;

/**
 * Scénarios de recette REC-021 à REC-036 (module 15).
 *
 * Les tests utilisent les services métier et les contrôleurs avec la même
 * transaction PostgreSQL que le reste de la suite : chaque scénario est isolé
 * et rejouable.
 */
final class Module15AcceptanceTest extends DatabaseTestCase
{
    public function testRec021ContractAndMilestonesComputeExactTotals(): void
    {
        $user = $this->createUser('ROLE_REC021_MANAGER', ['contract.view_all', 'contract.manage']);
        $client = $this->createClientFixture('Client recette 021');
        $contract = $this->createContractFixture($client, $user, null, '100000.000');

        $this->createMilestoneFixture($contract, '40000.000', CompletionStatus::Completed, ContractBillingStatus::ReadyToInvoice, new \DateTimeImmutable('+10 days'));
        $this->createMilestoneFixture($contract, '60000.000', CompletionStatus::Upcoming, ContractBillingStatus::NotBillable, new \DateTimeImmutable('+40 days'));

        self::assertSame('100000.000', $contract->getMilestonesTotalHt());
        self::assertSame('0.000', $contract->getInvoicedTotalHt());
        self::assertSame('100000.000', $this->billingService()->remainingToInvoice($contract));
        self::assertCount(2, $contract->getMilestones());
    }

    public function testRec022ReadyMilestoneIsNotBilledBeforeInvoiceValidation(): void
    {
        $user = $this->createUser('ROLE_REC022_MANAGER', ['contract.view_all', 'invoice.manage']);
        $client = $this->createClientFixture('Client recette 022');
        $contract = $this->createContractFixture($client, $user, null, '10000.000');
        $milestone = $this->createMilestoneFixture($contract, '10000.000', CompletionStatus::Completed, ContractBillingStatus::ReadyToInvoice);

        $invoice = $this->invoiceService()->createDraft($client, null, $contract, $milestone, $user);
        $this->entityManager->flush();

        self::assertSame(ContractBillingStatus::ReadyToInvoice, $milestone->getBillingStatus());
        self::assertSame('0.000', $milestone->getBilledAmountHt());
        self::assertSame('0.000', $contract->getInvoicedTotalHt());
        self::assertSame('10000.000', $this->billingService()->remainingToInvoice($contract));
        self::assertSame('10000.000', $invoice->getAmountHt());
    }

    public function testRec023InvoiceValidationReportsBillingOnMilestone(): void
    {
        $user = $this->createUser('ROLE_REC023_MANAGER', ['contract.view_all', 'invoice.manage']);
        $client = $this->createClientFixture('Client recette 023');
        $contract = $this->createContractFixture($client, $user, null, '50000.000');
        $milestone = $this->createMilestoneFixture($contract, '10000.000', CompletionStatus::Completed, ContractBillingStatus::ReadyToInvoice);

        $invoice = $this->invoiceService()->createDraft($client, null, $contract, $milestone, $user);
        $this->entityManager->flush();
        $this->invoiceService()->validate($invoice, $user);
        $this->entityManager->flush();

        self::assertSame(ContractBillingStatus::FullyInvoiced, $milestone->getBillingStatus());
        self::assertSame('10000.000', $milestone->getBilledAmountHt());
        self::assertSame($invoice->getNumber(), $milestone->getInvoiceReference());
        self::assertSame('10000.000', $contract->getInvoicedTotalHt());
        self::assertSame('40000.000', $this->billingService()->remainingToInvoice($contract));
    }

    public function testRec024InvoiceCreationIsRefusedWhenMilestoneIsNotReady(): void
    {
        $user = $this->createUser('ROLE_REC024_MANAGER', ['contract.view_all', 'contract.bill']);
        $client = $this->createClientFixture('Client recette 024');
        $contract = $this->createContractFixture($client, $user);
        $milestone = $this->createMilestoneFixture($contract, '12000.000', CompletionStatus::Upcoming, ContractBillingStatus::NotBillable, new \DateTimeImmutable('+20 days'));

        $this->client->loginUser($user);
        // L'écran n'affiche le bouton de facturation que pour une échéance
        // prête. On récupère donc le jeton CSRF dans cet état, puis on remet
        // l'échéance à « non facturable » pour vérifier le garde-fou serveur.
        $milestone->setBillingStatus(ContractBillingStatus::ReadyToInvoice);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/contrats/facturation');
        self::assertResponseIsSuccessful();
        $token = $crawler
            ->filterXPath('//form[contains(@action, "'.$milestone->getIdString().'/action")]')
            ->filter('input[name="_token"]')
            ->attr('value');
        self::assertNotEmpty($token);

        $milestone->setBillingStatus(ContractBillingStatus::NotBillable);
        $this->entityManager->flush();

        $this->client->request('POST', '/contrats/facturation/'.$milestone->getIdString().'/action', [
            '_token' => $token,
            'action' => 'invoice',
        ]);

        self::assertResponseRedirects('/contrats/facturation');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(ContractMilestone::class)->find($milestone->getIdString());
        self::assertInstanceOf(ContractMilestone::class, $reloaded);
        self::assertSame(ContractBillingStatus::NotBillable, $reloaded->getBillingStatus());
        self::assertSame('0.000', $reloaded->getBilledAmountHt());
    }

    public function testRec028ReceiptReservationsAreLiftedIndividually(): void
    {
        $user = $this->createUser('ROLE_REC028_MANAGER', ['contract.view_all', 'contract.manage']);
        $client = $this->createClientFixture('Client recette 028');
        $contract = $this->createContractFixture($client, $user);

        $receipt = (new ContractReceipt())
            ->setContract($contract)
            ->setReceiptType(ReceiptType::Provisional)
            ->setPlannedDate(new \DateTimeImmutable('2026-09-30'))
            ->setActualDate(new \DateTimeImmutable('2026-10-01'))
            ->setPvReference('PV-REC-028');
        $contract->addReceipt($receipt);

        $first = (new ContractReservation())
            ->setDescription('Réserve 1')
            ->setStatus(ReservationStatus::Open)
            ->setPlannedResolutionDate(new \DateTimeImmutable('+10 days'));
        $second = (new ContractReservation())
            ->setDescription('Réserve 2')
            ->setStatus(ReservationStatus::Open)
            ->setPlannedResolutionDate(new \DateTimeImmutable('+20 days'));
        $receipt->addReservation($first)->addReservation($second);

        $this->entityManager->persist($receipt);
        $this->entityManager->flush();
        self::assertSame(2, $receipt->getOpenReservationsCount());

        $first->lift(new \DateTimeImmutable('today'));
        $receipt->refreshReservationsStatus();
        self::assertSame(1, $receipt->getOpenReservationsCount());
        self::assertSame(\App\Enum\ReservationsStatus::PartiallyLifted, $receipt->getReservationsStatus());

        $second->lift(new \DateTimeImmutable('today'));
        $receipt->refreshReservationsStatus();
        self::assertSame(0, $receipt->getOpenReservationsCount());
        self::assertSame(\App\Enum\ReservationsStatus::Lifted, $receipt->getReservationsStatus());
    }

    public function testRec029AcceptedRenewalCreatesNewContractAndClosesPrevious(): void
    {
        $user = $this->createUser('ROLE_REC029_MANAGER', ['contract.view_all', 'contract.renew']);
        $client = $this->createClientFixture('Client recette 029');
        $contract = $this->createContractFixture($client, $user, null, '80000.000');
        $contract->setEffectiveEndDate(new \DateTimeImmutable('2026-12-31'));
        $contract->setEffectiveStartDate(new \DateTimeImmutable('2026-01-01'));
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/contrats/'.$contract->getIdString().'/renouvellement');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer la décision')->form([
            'contract_renewal_form[decisionStatus]' => 'accepted',
            'contract_renewal_form[newStartDate]' => '2027-01-01',
            'contract_renewal_form[newEndDate]' => '2027-12-31',
            'contract_renewal_form[newAmountHt]' => '85000.000',
            'contract_renewal_form[notes]' => 'Reconduction explicite acceptée.',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $previous = $this->entityManager->getRepository(\App\Entity\Contract::class)->find($contract->getIdString());
        self::assertInstanceOf(\App\Entity\Contract::class, $previous);
        self::assertSame(ContractStatus::Closed, $previous->getStatus());

        $renewals = $previous->getRenewals();
        self::assertCount(1, $renewals);
        $renewal = $renewals->first();
        self::assertInstanceOf(\App\Entity\ContractRenewal::class, $renewal);
        self::assertSame('accepted', $renewal->getDecisionStatus()->value);
        self::assertNotNull($renewal->getNewContract());
        self::assertSame('85000.000', $renewal->getNewContract()->getAmountHt());
    }

    public function testRec030RetentionReleaseReducesRemainingAmount(): void
    {
        $user = $this->createUser('ROLE_REC030_MANAGER', ['contract.view_all', 'contract.manage']);
        $client = $this->createClientFixture('Client recette 030');
        $contract = $this->createContractFixture($client, $user);

        $retention = (new RetentionGuarantee())
            ->setAmount('10000.000')
            ->setCurrencyCode('TND')
            ->setEligibilityDate(new \DateTimeImmutable('+30 days'));
        $contract->addRetentionGuarantee($retention);
        $this->entityManager->persist($retention);
        $this->entityManager->flush();

        $retention->release('4000.000', new \DateTimeImmutable('today'));
        self::assertSame('4000.000', $retention->getReleasedAmount());
        self::assertSame('6000.000', $retention->getRemainingToRelease());
        self::assertSame(RetentionStatus::ReleaseRequested, $retention->getStatus());

        $retention->release('6000.000', new \DateTimeImmutable('today'));
        self::assertSame('10000.000', $retention->getReleasedAmount());
        self::assertSame('0.000', $retention->getRemainingToRelease());
        self::assertSame(RetentionStatus::Released, $retention->getStatus());
    }

    public function testRec031ContractCalendarListsUpcomingDeadline(): void
    {
        $user = $this->createUser('ROLE_REC031_READER', ['contract.view_all']);
        $client = $this->createClientFixture('Client recette 031');
        $contract = $this->createContractFixture($client, $user);
        $contract->setEffectiveEndDate(new \DateTimeImmutable('+45 days'));
        $contract->setEffectiveStartDate(new \DateTimeImmutable('-30 days'));
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/contrats/calendrier?horizon=6');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($contract->getReference(), (string) $this->client->getResponse()->getContent());
    }

    public function testRec032PostponedMilestoneKeepsHistory(): void
    {
        $user = $this->createUser('ROLE_REC032_MANAGER', ['contract.view_all', 'contract.bill']);
        $client = $this->createClientFixture('Client recette 032');
        $contract = $this->createContractFixture($client, $user);
        $milestone = $this->createMilestoneFixture(
            $contract,
            '15000.000',
            CompletionStatus::Upcoming,
            ContractBillingStatus::NotBillable,
            new \DateTimeImmutable('2026-11-15'),
        );

        $newDate = new \DateTimeImmutable('2026-12-15');
        $this->billingService()->postpone($milestone, $newDate, 'Attente de validation du client', $user);
        $this->entityManager->flush();

        self::assertSame($newDate, $milestone->getPlannedDate());
        self::assertCount(1, $milestone->getHistory());
        $history = $milestone->getHistory()->first();
        self::assertInstanceOf(\App\Entity\ContractMilestoneHistory::class, $history);
        self::assertSame('2026-12-15', $history->getNewPlannedDate()?->format('Y-m-d'));
        self::assertSame('Attente de validation du client', $history->getReason());
    }

    public function testRec033MaintenanceMilestoneGenerationIsIdempotent(): void
    {
        $user = $this->createUser('ROLE_REC033_MANAGER', ['contract.view_all', 'contract.manage']);
        $client = $this->createClientFixture('Client recette 033');
        $contract = $this->createContractFixture($client, $user);

        $maintenance = (new MaintenanceContract())
            ->setReference('MNT-REC-033')
            ->setContract($contract)
            ->setOriginalContract($contract)
            ->setMaintenanceStartDate(new \DateTimeImmutable('2026-01-01'))
            ->setMaintenanceEndDate(new \DateTimeImmutable('2026-12-31'))
            ->setDurationMonths(12)
            ->setBillingFrequency(BillingFrequency::Monthly)
            ->setPeriodicAmountHt('1000.000')
            ->setVatRate('19.0000')
            ->setCommercialResponsible($user);
        $contract->addMaintenanceContract($maintenance);
        $this->entityManager->persist($maintenance);
        $this->entityManager->flush();

        $created = $this->maintenanceService()->generate($maintenance);
        self::assertCount(12, $created);

        $again = $this->maintenanceService()->generate($maintenance);
        self::assertCount(0, $again);
        self::assertCount(12, $this->entityManager->getRepository(ContractMilestone::class)->findBy(['maintenanceContract' => $maintenance]));
    }

    public function testRec034AlertDelaysFollowConfiguration(): void
    {
        $user = $this->createUser('ROLE_REC034_MANAGER', ['contract.view_all', 'contract.notify']);
        $client = $this->createClientFixture('Client recette 034');
        $contract = $this->createContractFixture($client, $user);
        $today = new \DateTimeImmutable('2026-10-22');
        $contract->setEffectiveEndDate($today->modify('+40 days'));
        $contract->setStatus(ContractStatus::InProgress);
        $this->entityManager->flush();

        $created = $this->notificationService()->generateForContract($contract, $today);
        self::assertCount(2, $created);
        $delays = array_map(static fn ($notification): int => $notification->getAlertDelayDays(), $created);
        self::assertContains(90, $delays);
        self::assertContains(60, $delays);
        self::assertNotContains(30, $delays);
        self::assertNotContains(7, $delays);
    }

    public function testRec035AlertGenerationIsIdempotent(): void
    {
        $user = $this->createUser('ROLE_REC035_MANAGER', ['contract.view_all', 'contract.notify']);
        $client = $this->createClientFixture('Client recette 035');
        $contract = $this->createContractFixture($client, $user);
        $today = new \DateTimeImmutable('2026-10-22');
        $contract->setEffectiveEndDate($today->modify('+40 days'));
        $contract->setStatus(ContractStatus::InProgress);
        $this->entityManager->flush();

        $first = $this->notificationService()->generateForContract($contract, $today);
        $second = $this->notificationService()->generateForContract($contract, $today);

        self::assertCount(2, $first);
        self::assertCount(0, $second);
        self::assertCount(2, $this->entityManager->getRepository(\App\Entity\ContractNotification::class)->findBy(['contract' => $contract]));
    }

    public function testRec036BillingToPerformAggregatesReadyAndOverdueMilestones(): void
    {
        $user = $this->createUser('ROLE_REC036_MANAGER', ['contract.view_all', 'contract.bill']);
        $client = $this->createClientFixture('Client recette 036');
        $contract = $this->createContractFixture($client, $user, null, '30000.000');

        $this->createMilestoneFixture($contract, '10000.000', CompletionStatus::Completed, ContractBillingStatus::ReadyToInvoice, new \DateTimeImmutable('-5 days'));
        $this->createMilestoneFixture($contract, '5000.000', CompletionStatus::Completed, ContractBillingStatus::ReadyToInvoice, new \DateTimeImmutable('+15 days'));
        $this->createMilestoneFixture($contract, '7000.000', CompletionStatus::Completed, ContractBillingStatus::Blocked, new \DateTimeImmutable('-10 days'));
        $this->createMilestoneFixture($contract, '8000.000', CompletionStatus::Completed, ContractBillingStatus::FullyInvoiced, new \DateTimeImmutable('-15 days'));

        self::assertSame('15000.000', $this->billingService()->readyToInvoiceTotalHt());
        self::assertSame('10000.000', $this->billingService()->overdueTotalHt(new \DateTimeImmutable('2026-10-22')));
        self::assertSame('10000.000', $this->billingService()->overdueTotalHt(new \DateTimeImmutable('2026-10-22'), $contract));
    }

    private function billingService(): ContractBillingService
    {
        return static::getContainer()->get(ContractBillingService::class);
    }

    private function invoiceService(): InvoiceService
    {
        return static::getContainer()->get(InvoiceService::class);
    }

    private function maintenanceService(): MaintenanceScheduleService
    {
        return static::getContainer()->get(MaintenanceScheduleService::class);
    }

    private function notificationService(): ContractNotificationService
    {
        return static::getContainer()->get(ContractNotificationService::class);
    }
}
