<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Entity\ContractNotification;
use App\Repository\ContractNotificationRepository;
use App\Service\Settings\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Envoi des rappels contractuels par email.
 *
 * Seules les alertes actives, non encore envoyées et dont la date de
 * déclenchement est atteinte sont traitées. Un échec est enregistré sur la
 * notification et réessayé lors des exécutions suivantes (trois tentatives au
 * maximum), sans jamais bloquer les autres rappels.
 */
final class ContractReminderMailer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContractNotificationRepository $notificationRepository,
        private readonly SettingsProvider $settingsProvider,
        private readonly CompanyMailer $companyMailer,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, reason?: string}
     */
    public function sendPending(int $limit = 100): array
    {
        $settings = $this->settingsProvider->mail();

        if (!$settings->isEnabled()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'reason' => "L'envoi des emails est désactivé. Activez-le dans Administration > Paramètres email.",
            ];
        }

        if (!$settings->isConfigured()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'reason' => 'La configuration SMTP est incomplète. Renseignez le serveur, le port et l\'adresse d\'expédition.',
            ];
        }

        $limit = max(1, min(500, $limit));
        $notifications = $this->notificationRepository->findEmailCandidates($limit, new \DateTimeImmutable('today'));
        $company = $this->settingsProvider->company();
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($notifications as $notification) {
            $contract = $notification->getContract();
            $recipient = $notification->getRecipient();

            if (null === $contract || null === $recipient || '' === trim($recipient->getEmail())) {
                ++$skipped;
                continue;
            }

            try {
                $email = (new Email())
                    ->from(new Address($settings->getSenderEmail(), $settings->getSenderName()))
                    ->to($recipient->getEmail())
                    ->subject(sprintf(
                        'Rappel contrat %s - %s',
                        $contract->getReference(),
                        $notification->getNotificationType()->label(),
                    ))
                    ->html($this->twig->render('emails/contract_reminder.html.twig', $this->context($notification)))
                    ->text($this->twig->render('emails/contract_reminder.txt.twig', $this->context($notification)));

                if (null !== $company->getEmail() && '' !== $company->getEmail()) {
                    $email->replyTo($company->getEmail());
                }

                $this->companyMailer->send($email, $settings);
                $notification->markEmailSent();
                ++$sent;
            } catch (\Throwable $exception) {
                $notification->markEmailFailed(mb_substr($exception->getMessage(), 0, 1000));
                ++$failed;
                $this->logger->warning('Échec d\'envoi du rappel contractuel.', [
                    'notification' => $notification->getIdString(),
                    'contract' => $contract->getReference(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ([] !== $notifications) {
            $this->entityManager->flush();
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(ContractNotification $notification): array
    {
        $company = $this->settingsProvider->company();
        $contract = $notification->getContract();

        return [
            'notification' => $notification,
            'contract' => $contract,
            'recipient' => $notification->getRecipient(),
            'company' => $company,
            'contract_url' => null !== $contract ? $this->absoluteUrl('app_contract_show', ['id' => $contract->getIdString()]) : null,
            'logo_url' => $company->hasLogo() ? $this->absoluteUrl('app_company_logo') : null,
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function absoluteUrl(string $route, array $parameters = []): string
    {
        return rtrim($this->baseUrl, '/').$this->urlGenerator->generate(
            $route,
            $parameters,
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }
}