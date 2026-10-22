<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Entity\MailSettings;
use App\Enum\MailEncryption;
use App\Service\Settings\SecretCipher;
use App\Service\Settings\SettingsProvider;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Envoi d'emails via le serveur SMTP configuré dans l'interface.
 *
 * Le transport est construit à la demande à partir des paramètres en base :
 * une modification de la configuration est donc prise en compte sans
 * redémarrage du conteneur ni reconstruction du conteneur Symfony.
 */
final class CompanyMailer
{
    public function __construct(
        private readonly SettingsProvider $settingsProvider,
        private readonly SecretCipher $cipher,
        private readonly Environment $twig,
    ) {
    }

    public function send(Email $email, ?MailSettings $settings = null): void
    {
        $settings ??= $this->settingsProvider->mail();

        if (!$settings->isConfigured()) {
            throw new MailNotConfiguredException("La configuration SMTP est incomplète. Renseignez le serveur, le port et l'adresse d'expédition.");
        }

        $transport = Transport::fromDsn($this->dsn($settings));
        (new Mailer($transport))->send($email);
    }

    /**
     * Envoie un email de vérification avec les paramètres enregistrés, même si
     * l'envoi automatique est encore désactivé.
     */
    public function sendTest(string $recipient): void
    {
        $settings = $this->settingsProvider->mail();
        $company = $this->settingsProvider->company();

        if (!$settings->isConfigured()) {
            throw new MailNotConfiguredException("La configuration SMTP est incomplète. Enregistrez d'abord le serveur, le port et l'adresse d'expédition.");
        }

        $email = (new Email())
            ->from($this->fromAddress($settings))
            ->to($recipient)
            ->subject(sprintf('Test de configuration email - %s', $company->getDisplayName()))
            ->html($this->twig->render('emails/test.html.twig', [
                'company' => $company,
                'settings' => $settings,
            ]))
            ->text($this->twig->render('emails/test.txt.twig', [
                'company' => $company,
                'settings' => $settings,
            ]));

        if (null !== $company->getEmail() && '' !== $company->getEmail()) {
            $email->replyTo($company->getEmail());
        }

        $this->send($email, $settings);
    }

    /** DSN Symfony Mailer construit à partir de la fiche SMTP. */
    public function dsn(MailSettings $settings): string
    {
        $scheme = MailEncryption::Tls === $settings->getEncryption() ? 'smtps' : 'smtp';
        $credentials = '';

        if (null !== $settings->getUsername() && '' !== $settings->getUsername()) {
            $credentials = rawurlencode($settings->getUsername());
            if ($settings->hasPassword()) {
                $password = $this->cipher->decrypt($settings->getPasswordEncrypted());
                if (null === $password) {
                    throw new MailNotConfiguredException('Le mot de passe SMTP enregistré est illisible. Veuillez le ressaisir.');
                }
                $credentials .= ':'.rawurlencode($password);
            }
            $credentials .= '@';
        }

        $options = [];
        if (MailEncryption::None === $settings->getEncryption()) {
            $options['auto_tls'] = 'false';
        } elseif (MailEncryption::StartTls === $settings->getEncryption()) {
            $options['require_tls'] = 'true';
        }

        $query = [] === $options ? '' : '?'.http_build_query($options);

        return sprintf('%s://%s%s:%d%s', $scheme, $credentials, $settings->getHost(), $settings->getPort(), $query);
    }

    private function fromAddress(MailSettings $settings): Address
    {
        return new Address($settings->getSenderEmail(), $settings->getSenderName());
    }
}