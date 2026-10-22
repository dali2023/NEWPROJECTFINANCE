<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MailSettings;
use App\Form\MailSettingsFormType;
use App\Repository\ContractNotificationRepository;
use App\Repository\MailSettingsRepository;
use App\Service\AuditLogger;
use App\Service\Mail\CompanyMailer;
use App\Service\Settings\SecretCipher;
use App\Service\Settings\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Paramétrage SMTP pour l'envoi des rappels contractuels.
 */
#[Route('/admin/parametres/email', name: 'app_admin_settings_mail')]
#[IsGranted('admin.settings')]
final class MailSettingsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        MailSettingsRepository $repository,
        ContractNotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
        SettingsProvider $settingsProvider,
        SecretCipher $cipher,
        AuditLogger $auditLogger,
    ): Response {
        $existing = $repository->findSingleton();
        $mail = $existing ?? new MailSettings();
        $isNew = null === $existing;

        if ($isNew) {
            $company = $settingsProvider->company();
            $mail->setSenderName($company->getDisplayName());
            if (null !== $company->getEmail()) {
                $mail->setSenderEmail($company->getEmail());
            }
        }

        $before = $this->snapshot($mail);
        $form = $this->createForm(MailSettingsFormType::class, $mail);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $clearPassword = (bool) $form->get('clearPassword')->getData();

            if ($clearPassword) {
                $mail->setPasswordEncrypted(null);
            } elseif (\is_string($plainPassword) && '' !== trim($plainPassword)) {
                $mail->setPasswordEncrypted($cipher->encrypt($plainPassword));
            }

            $entityManager->persist($mail);
            $entityManager->flush();
            $settingsProvider->reset();

            if ($mail->isEnabled()) {
                // Une nouvelle configuration SMTP doit permettre de réessayer
                // les rappels bloqués après trois échecs.
                $notificationRepository->resetFailedEmailAttempts();
            }

            $auditLogger->log(
                $isNew ? 'create' : 'update',
                'MailSettings',
                $mail->getIdString(),
                $isNew ? null : $before,
                $this->snapshot($mail),
                'success',
                'M01',
                'Mise à jour des paramètres email',
            );

            $this->addFlash('success', 'Les paramètres email ont été enregistrés.');

            return $this->redirectToRoute('app_admin_settings_mail');
        }

        return $this->render('admin/settings/mail.html.twig', [
            'form' => $form,
            'mail' => $mail,
            'is_new' => $isNew,
        ]);
    }

    #[Route('/test', name: '_test', methods: ['POST'])]
    public function test(
        Request $request,
        CompanyMailer $companyMailer,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('mail_settings_test', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $recipient = trim($request->request->getString('test_email'));
        if (false === filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Saisissez une adresse électronique valide pour le test.');

            return $this->redirectToRoute('app_admin_settings_mail');
        }

        try {
            $companyMailer->sendTest($recipient);
            $auditLogger->log('send_test', 'MailSettings', null, null, [
                'recipient' => $recipient,
            ], 'success', 'M01', 'Email de test SMTP');

            $this->addFlash('success', sprintf('Email de test envoyé à %s.', $recipient));
        } catch (\Throwable $exception) {
            $auditLogger->log('send_test', 'MailSettings', null, null, [
                'recipient' => $recipient,
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ], 'failure', 'M01', 'Échec de l’email de test SMTP');

            $this->addFlash('error', 'Échec de l’envoi : '.$exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_settings_mail');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(MailSettings $mail): array
    {
        return [
            'isEnabled' => $mail->isEnabled(),
            'host' => $mail->getHost(),
            'port' => $mail->getPort(),
            'encryption' => $mail->getEncryption()->value,
            'username' => $mail->getUsername(),
            'passwordSet' => $mail->hasPassword(),
            'senderEmail' => $mail->getSenderEmail(),
            'senderName' => $mail->getSenderName(),
        ];
    }
}