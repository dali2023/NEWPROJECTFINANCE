<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Entity\CashMovement;
use App\Entity\Expense;
use App\Entity\Partner;
use App\Entity\PartnerTransaction;
use App\Enum\ExpenseCategory;
use App\Enum\ExpenseStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PartnerTransactionStatus;
use App\Enum\PartnerTransactionType;
use App\Enum\PaymentMethod;
use App\Enum\TreasuryDirection;
use App\Enum\TreasuryMovementType;
use App\Service\ExpenseService;
use App\Service\InvoiceService;
use App\Service\PartnerService;
use App\Tests\Support\DatabaseTestCase;

/**
 * Scénarios de recette REC-025 à REC-027 (facturation, charges, trésorerie).
 */
final class FinanceAcceptanceTest extends DatabaseTestCase
{
    public function testRec025ExpenseAuthorCannotApproveOwnExpense(): void
    {
        $author = $this->createUser('ROLE_REC025_AUTHOR', ['expense.propose', 'expense.approve']);
        $expense = $this->createExpenseFixture($author);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('auteur');

        $this->expenseService()->approve($expense, $author);
    }

    public function testRec026DraftInvoiceCannotBeCollected(): void
    {
        $user = $this->createUser('ROLE_REC026_FINANCE', ['invoice.manage']);
        $client = $this->createClientFixture('Client recette 026');
        $invoice = $this->invoiceService()->createDraft($client, null, null, null, $user);
        $this->entityManager->flush();

        self::assertSame(InvoiceStatus::Draft, $invoice->getStatus());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('validée');

        $this->invoiceService()->registerPayment(
            $invoice,
            '1000.000',
            new \DateTimeImmutable('2026-10-22'),
            PaymentMethod::BankTransfer,
            null,
            null,
            null,
            $user,
        );
    }

    public function testRec027PartialPaymentUpdatesBalanceAndCreatesTreasuryMovement(): void
    {
        $user = $this->createUser('ROLE_REC027_FINANCE', ['invoice.manage', 'treasury.manage']);
        $client = $this->createClientFixture('Client recette 027');
        $contract = $this->createContractFixture($client, $user, null, '30000.000');
        $milestone = $this->createMilestoneFixture(
            $contract,
            '10000.000',
            \App\Enum\CompletionStatus::Completed,
            \App\Enum\ContractBillingStatus::ReadyToInvoice,
        );
        $account = $this->createBankAccountFixture($user, '100000.000');

        $invoice = $this->invoiceService()->createDraft($client, null, $contract, $milestone, $user);
        $this->entityManager->flush();
        $this->invoiceService()->validate($invoice, $user);

        $payment = $this->invoiceService()->registerPayment(
            $invoice,
            '4000.000',
            new \DateTimeImmutable('2026-10-22'),
            PaymentMethod::BankTransfer,
            $account,
            'VIR-REC-027',
            null,
            $user,
        );
        $this->entityManager->flush();

        self::assertSame(InvoiceStatus::PartiallyPaid, $invoice->getStatus());
        self::assertSame('4000.000', $invoice->getPaidAmount());
        self::assertSame('7900.000', $invoice->getBalance());
        self::assertSame('104000.000', $account->getBalance());

        $movement = $this->entityManager->getRepository(CashMovement::class)->findOneBy([
            'sourceType' => 'Payment',
            'sourceId' => $payment->getIdString(),
        ]);
        self::assertInstanceOf(CashMovement::class, $movement);
        self::assertSame(TreasuryDirection::Inflow, $movement->getDirection());
        self::assertSame(TreasuryMovementType::ClientReceipt, $movement->getMovementType());
        self::assertSame('4000.000', $movement->getAmount());
        self::assertSame('VIR-REC-027', $movement->getNotes());
    }

    public function testApprovedExpensePaymentCreatesOutflowAndUpdatesBalance(): void
    {
        $author = $this->createUser('ROLE_EXP_PAY_AUTHOR', ['expense.propose']);
        $approver = $this->createUser('ROLE_EXP_PAY_APPROVER', ['expense.approve']);
        $account = $this->createBankAccountFixture($author, '100000.000');
        $expense = $this->createExpenseFixture($author);

        $this->expenseService()->approve($expense, $approver);
        $payment = $this->expenseService()->registerPayment(
            $expense,
            '500.000',
            new \DateTimeImmutable('2026-10-22'),
            PaymentMethod::BankTransfer,
            $account,
            'VIR-DEP-001',
            null,
            $approver,
        );
        $this->entityManager->flush();

        self::assertSame(ExpenseStatus::PartiallyPaid, $expense->getStatus());
        self::assertSame('500.000', $expense->getPaidAmount());
        self::assertSame('690.000', $expense->getBalance());
        self::assertSame('99500.000', $account->getBalance());

        $movement = $this->entityManager->getRepository(CashMovement::class)->findOneBy([
            'sourceType' => 'ExpensePayment',
            'sourceId' => $payment->getIdString(),
        ]);
        self::assertInstanceOf(CashMovement::class, $movement);
        self::assertSame(TreasuryDirection::Outflow, $movement->getDirection());
        self::assertSame(TreasuryMovementType::ExpensePayment, $movement->getMovementType());
        self::assertSame('500.000', $movement->getAmount());
    }

    public function testPartnerSettlementRequiresValidationAndCreatesTreasuryOutflow(): void
    {
        $user = $this->createUser('ROLE_PARTNER_TEST', ['partner.manage', 'partner.validate', 'treasury.manage']);
        $account = $this->createBankAccountFixture($user, '100000.000');
        $partner = (new Partner())
            ->setCode('ASS-REC-001')
            ->setName('Associé recette')
            ->setPartnerType(\App\Enum\PartnerType::Individual)
            ->setOwnershipPercent('100.0000')
            ->setIsActive(true)
            ->setCreatedBy($user);
        $transaction = (new PartnerTransaction())
            ->setReference('PT-REC-001')
            ->setPartner($partner)
            ->setTransactionType(PartnerTransactionType::CurrentAccountRepayment)
            ->setStatus(PartnerTransactionStatus::Planned)
            ->setTransactionDate(new \DateTimeImmutable('2026-10-22'))
            ->setAmount('3000.000')
            ->setCurrencyCode('TND')
            ->setLabel('Remboursement de compte courant')
            ->setCreatedBy($user);
        $partner->addTransaction($transaction);
        $this->entityManager->persist($partner);
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $blocked = false;
        try {
            $this->partnerService()->pay($transaction, $account, $user);
        } catch (\LogicException) {
            $blocked = true;
        }
        self::assertTrue($blocked, 'Une opération non validée ne doit pas être réglée.');

        $this->partnerService()->validate($transaction, $user);
        $this->partnerService()->pay($transaction, $account, $user);
        $this->entityManager->flush();

        self::assertSame(PartnerTransactionStatus::Paid, $transaction->getStatus());
        self::assertSame('97000.000', $account->getBalance());

        $movement = $this->entityManager->getRepository(CashMovement::class)->findOneBy([
            'sourceType' => 'PartnerTransaction',
            'sourceId' => $transaction->getIdString(),
        ]);
        self::assertInstanceOf(CashMovement::class, $movement);
        self::assertSame(TreasuryDirection::Outflow, $movement->getDirection());
        self::assertSame(TreasuryMovementType::PartnerRepayment, $movement->getMovementType());
        self::assertSame('3000.000', $movement->getAmount());
    }

    private function createExpenseFixture(\App\Entity\User $author): Expense
    {
        $expense = (new Expense())
            ->setReference('DEP-REC-025')
            ->setCategory(ExpenseCategory::Other)
            ->setStatus(ExpenseStatus::Submitted)
            ->setLabel('Charge soumise par son auteur')
            ->setAmountHt('1000.000')
            ->setVatRate('19.0000')
            ->setExpenseDate(new \DateTimeImmutable('2026-10-22'))
            ->setCreatedBy($author);

        $this->entityManager->persist($expense);
        $this->entityManager->flush();

        return $expense;
    }

    private function expenseService(): ExpenseService
    {
        return static::getContainer()->get(ExpenseService::class);
    }

    private function invoiceService(): InvoiceService
    {
        return static::getContainer()->get(InvoiceService::class);
    }

    private function partnerService(): PartnerService
    {
        return static::getContainer()->get(PartnerService::class);
    }
}
