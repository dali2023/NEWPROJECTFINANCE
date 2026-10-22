<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use App\Entity\ContractMilestone;
use App\Entity\ContractNotification;
use App\Entity\ContractReceipt;
use App\Entity\ContractReservation;
use App\Entity\RetentionGuarantee;
use App\Entity\User;
use App\Enum\ContractNotificationSeverity;
use App\Enum\ContractNotificationStatus;
use App\Enum\ContractNotificationType;
use App\Enum\ReceiptType;
use App\Repository\ContractNotificationRepository;
use App\Repository\ContractRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Génération idempotente des alertes contractuelles (§15.7).
 *
 * Une notification est identifiée par la combinaison contrat + événement +
 * date d'événement + délai + destinataire. Deux exécutions successives ne
 * produisent donc jamais de doublon (REC-035), tandis qu'une échéance reportée
 * produit une nouvelle alerte fondée sur sa nouvelle date validée (REC-032).
 */
final class ContractNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContractNotificationRepository $notificationRepository,
        private readonly ContractRepository $contractRepository,
        private readonly ContractAlertConfig $alertConfig,
        private readonly ContractDateCalculator $dateCalculator,
    ) {
    }

    /**
     * @return list<ContractNotification>
     */
    public function generateForContract(Contract $contract, ?\DateTimeImmutable $today = null, bool $flush = true): array
    {
        $today = ($today ?? $this->dateCalculator->today())->setTime(0, 0);
        $created = [];
        $seen = [];

        foreach ($this->eventsFor($contract, $today) as $event) {
            $type = $event['type'];
            $eventDate = $event['event_date'];
            $recipient = $event['recipient'];

            if (null === $recipient) {
                continue;
            }

            foreach ($this->alertConfig->delaysFor($type) as $delay) {
                // Une alerte avant échéance n'est pas créée après l'événement ;
                // une alerte « après échéance » n'est créée qu'après celui-ci.
                if ($delay >= 0 && $eventDate < $today) {
                    continue;
                }
                if ($delay < 0 && $eventDate >= $today) {
                    continue;
                }

                $triggerDate = $this->triggerDate($eventDate, $delay);
                if ($triggerDate > $today) {
                    continue;
                }

                $key = $this->uniqueKey($contract, $event, $delay, $recipient);
                if (isset($seen[$key]) || null !== $this->notificationRepository->findByUniqueKey($key)) {
                    continue;
                }
                $seen[$key] = true;

                $notification = new ContractNotification();
                $notification->setContract($contract)
                    ->setNotificationType($type)
                    ->setEventDate($eventDate)
                    ->setTriggerDate($triggerDate)
                    ->setAlertDelayDays($delay)
                    ->setSeverity($delay < 0 ? ContractNotificationSeverity::Critical : ContractNotificationSeverity::forDelay($delay))
                    ->setRecipient($recipient)
                    ->setStatus(ContractNotificationStatus::Pending)
                    ->setUniqueKey($key)
                    ->setTitle(sprintf('%s — %s', $type->label(), $contract->getReference()))
                    ->setMessage($this->messageFor($contract, $event, $delay, $triggerDate));

                if (isset($event['milestone'])) {
                    $notification->setMilestone($event['milestone']);
                }
                if (isset($event['receipt'])) {
                    $notification->setReceipt($event['receipt']);
                }
                if (isset($event['reservation'])) {
                    $notification->setReservation($event['reservation']);
                }
                if (isset($event['retention'])) {
                    $notification->setRetention($event['retention']);
                }

                $this->entityManager->persist($notification);
                $created[] = $notification;
            }
        }

        if ($flush && [] !== $created) {
            $this->entityManager->flush();
        }

        return $created;
    }

    /** Génère les alertes de tous les contrats actifs. */
    public function generateAll(?\DateTimeImmutable $today = null): int
    {
        $today ??= $this->dateCalculator->today();
        $created = [];

        foreach ($this->contractRepository->findActiveWithDates() as $contract) {
            $created = [...$created, ...$this->generateForContract($contract, $today, false)];
        }

        if ([] !== $created) {
            $this->entityManager->flush();
        }

        return \count($created);
    }

    /**
     * @return list<array{
     *     type: ContractNotificationType,
     *     event_date: \DateTimeImmutable,
     *     recipient: User|null,
     *     label: string,
     *     milestone?: ContractMilestone,
     *     receipt?: ContractReceipt,
     *     reservation?: ContractReservation,
     *     retention?: RetentionGuarantee
     * }>
     */
    private function eventsFor(Contract $contract, \DateTimeImmutable $today): array
    {
        $events = [];
        $fallbackRecipient = $contract->getResponsible() ?? $contract->getCreatedBy();

        if (null !== $contract->getEffectiveEndDate() && !$contract->getStatus()->isFrozen()) {
            $events[] = [
                'type' => ContractNotificationType::ContractExpiration,
                'event_date' => $contract->getEffectiveEndDate(),
                'recipient' => $fallbackRecipient,
                'label' => 'expiration du contrat',
            ];
        }

        foreach ($contract->getMaintenanceContracts() as $maintenance) {
            if (null === $maintenance->getMaintenanceEndDate() || $maintenance->getMaintenanceEndDate() < $today) {
                continue;
            }
            if (\in_array($maintenance->getRenewalStatus(), [
                \App\Enum\MaintenanceRenewalStatus::Renewed,
                \App\Enum\MaintenanceRenewalStatus::NotRenewed,
                \App\Enum\MaintenanceRenewalStatus::Terminated,
            ], true)) {
                continue;
            }

            $events[] = [
                'type' => ContractNotificationType::MaintenanceRenewal,
                'event_date' => $maintenance->getMaintenanceEndDate(),
                'recipient' => $maintenance->getCommercialResponsible() ?? $fallbackRecipient,
                'label' => 'renouvellement de maintenance',
            ];
        }

        foreach ($contract->getReceipts() as $receipt) {
            if (null !== $receipt->getPlannedDate()) {
                if (ReceiptType::Provisional === $receipt->getReceiptType() && null === $receipt->getActualDate()) {
                    $events[] = [
                        'type' => ContractNotificationType::ProvisionalReceipt,
                        'event_date' => $receipt->getPlannedDate(),
                        'recipient' => $fallbackRecipient,
                        'label' => 'réception provisoire',
                        'receipt' => $receipt,
                    ];
                }

                if (ReceiptType::Final === $receipt->getReceiptType() && !$receipt->isValidated()) {
                    $events[] = [
                        'type' => ContractNotificationType::FinalReceipt,
                        'event_date' => $receipt->getPlannedDate(),
                        'recipient' => $fallbackRecipient,
                        'label' => 'réception définitive',
                        'receipt' => $receipt,
                    ];
                }
            }

            $warrantyEnd = $this->dateCalculator->warrantyEndDateForReceipt($receipt, $contract);
            if (null !== $warrantyEnd && $warrantyEnd >= $today) {
                $events[] = [
                    'type' => ContractNotificationType::WarrantyEnd,
                    'event_date' => $warrantyEnd,
                    'recipient' => $fallbackRecipient,
                    'label' => 'fin de garantie',
                    'receipt' => $receipt,
                ];
            }

            foreach ($receipt->getReservations() as $reservation) {
                if (!$reservation->isOpen() || null === $reservation->getPlannedResolutionDate()) {
                    continue;
                }
                $events[] = [
                    'type' => ContractNotificationType::ReservationLifting,
                    'event_date' => $reservation->getPlannedResolutionDate(),
                    'recipient' => $reservation->getResponsible() ?? $fallbackRecipient,
                    'label' => 'levée des réserves',
                    'receipt' => $receipt,
                    'reservation' => $reservation,
                ];
            }
        }

        foreach ($contract->getMilestones() as $milestone) {
            if (null === $milestone->getPlannedDate()
                || $milestone->getCompletionStatus()->blocksBilling()
                || $milestone->getBillingStatus()->blocksBilling()
                || $milestone->isFullyInvoiced()
                || !\App\Service\Money::isPositive($milestone->getRemainingToInvoiceHt())) {
                continue;
            }

            $events[] = [
                'type' => ContractNotificationType::BillingDue,
                'event_date' => $milestone->getPlannedDate(),
                'recipient' => $milestone->getResponsible() ?? $fallbackRecipient,
                'label' => 'échéance de facturation '.$milestone->getReference(),
                'milestone' => $milestone,
            ];
        }

        foreach ($contract->getRetentionGuarantees() as $guarantee) {
            if (\App\Enum\RetentionStatus::Released === $guarantee->getStatus()
                || null === $guarantee->getEligibilityDate()
                || !\App\Service\Money::isPositive($guarantee->getRemainingToRelease())) {
                continue;
            }

            $events[] = [
                'type' => ContractNotificationType::RetentionRelease,
                'event_date' => $guarantee->getEligibilityDate(),
                'recipient' => $fallbackRecipient,
                'label' => 'libération de retenue de garantie',
                'retention' => $guarantee,
            ];
        }

        return $events;
    }

    private function triggerDate(\DateTimeImmutable $eventDate, int $delay): \DateTimeImmutable
    {
        $modifier = $delay >= 0
            ? sprintf('-%d days', $delay)
            : sprintf('+%d days', abs($delay));

        return $eventDate->modify($modifier);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function uniqueKey(Contract $contract, array $event, int $delay, User $recipient): string
    {
        $parts = [
            $contract->getIdString(),
            $event['type']->value,
            $event['event_date']->format('Y-m-d'),
            (string) $delay,
            $recipient->getIdString(),
            isset($event['milestone']) ? $event['milestone']->getIdString() : '',
            isset($event['receipt']) ? $event['receipt']->getIdString() : '',
            isset($event['reservation']) ? $event['reservation']->getIdString() : '',
            isset($event['retention']) ? $event['retention']->getIdString() : '',
        ];

        return implode('|', $parts);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function messageFor(Contract $contract, array $event, int $delay, \DateTimeImmutable $triggerDate): string
    {
        $date = $event['event_date']->format('d/m/Y');
        $client = $contract->getClient()?->getName() ?? 'client non renseigné';
        $context = sprintf('Contrat %s — %s (%s).', $contract->getReference(), $contract->getTitle(), $client);

        if (ContractNotificationType::BillingDue === $event['type'] && isset($event['milestone'])) {
            /** @var ContractMilestone $milestone */
            $milestone = $event['milestone'];

            return sprintf(
                '%s L’échéance %s « %s » était prévue le %s. Reste à facturer : %s %s.',
                $context,
                $milestone->getReference(),
                $milestone->getLabel(),
                $date,
                \App\Service\Money::format($milestone->getRemainingToInvoiceHt(), $contract->getCurrencyCode()),
                $delay < 0 ? '— alerte de retard.' : '',
            );
        }

        if (ContractNotificationType::RetentionRelease === $event['type'] && isset($event['retention'])) {
            /** @var RetentionGuarantee $retention */
            $retention = $event['retention'];

            return sprintf(
                '%s Retenue de garantie de %s : %s à libérer (éligibilité %s).',
                $context,
                \App\Service\Money::format($retention->getAmount(), $contract->getCurrencyCode()),
                \App\Service\Money::format($retention->getRemainingToRelease(), $contract->getCurrencyCode()),
                $date,
            );
        }

        if (ContractNotificationType::ReservationLifting === $event['type'] && isset($event['reservation'])) {
            return sprintf(
                '%s Réserve à lever avant le %s : %s',
                $context,
                $date,
                $event['reservation']->getDescription(),
            );
        }

        return sprintf('%s %s prévue le %s (alerte %s).', $context, ucfirst((string) $event['label']), $date, $this->delayLabel($delay));
    }

    private function delayLabel(int $delay): string
    {
        if ($delay < 0) {
            return 'après échéance';
        }

        return 0 === $delay ? 'à échéance' : 'J-'.$delay;
    }
}
