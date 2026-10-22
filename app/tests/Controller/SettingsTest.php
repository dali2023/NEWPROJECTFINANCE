<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CompanySettings;
use App\Entity\ContractNotification;
use App\Entity\MailSettings;
use App\Enum\ContractNotificationSeverity;
use App\Enum\ContractNotificationType;
use App\Enum\MailEncryption;
use App\Service\DocumentStorage;
use App\Service\Mail\CompanyMailer;
use App\Service\Settings\SecretCipher;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application as FrameworkApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Twig\Environment;

/**
 * Paramétrage de la société et du serveur SMTP.
 */
final class SettingsTest extends DatabaseTestCase
{
    public function testCompanySettingsRequiresTheDedicatedPermission(): void
    {
        $user = $this->createUser('ROLE_SETTINGS_DENIED', ['client.view']);
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/parametres/societe');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/parametres/email');
        self::assertResponseStatusCodeSame(403);
    }

    public function testCompanySettingsCanBeSavedWithALogo(): void
    {
        $user = $this->createUser('ROLE_SETTINGS_COMPANY', ['admin.settings']);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/admin/parametres/societe');
        self::assertResponseIsSuccessful();

        $logoPath = tempnam(sys_get_temp_dir(), 'one-erp-logo-').'.png';
        self::assertNotFalse($logoPath);
        file_put_contents($logoPath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z1S8AAAAASUVORK5CYII=',
        ));

        try {
            $form = $crawler->selectButton('Enregistrer')->form();
            $form['company_settings_form[legalName]']->setValue('Société Test SARL');
            $form['company_settings_form[tradeName]']->setValue('Société Test');
            $form['company_settings_form[taxId]']->setValue('1234567/A/M/000');
            $form['company_settings_form[email]']->setValue('contact@societe-test.tn');
            $form['company_settings_form[phone]']->setValue('+216 71 000 000');
            $form['company_settings_form[phoneSecondary]']->setValue('+216 98 000 000');
            $form['company_settings_form[address]']->setValue('12 rue du Test');
            $form['company_settings_form[postalCode]']->setValue('1000');
            $form['company_settings_form[city]']->setValue('Tunis');
            $form['company_settings_form[country]']->setValue('Tunisie');
            $form['company_settings_form[logo]']->upload($logoPath);

            $crawler = $this->client->submit($form);
            self::assertResponseRedirects('/admin/parametres/societe');

            $this->entityManager->clear();
            $company = $this->entityManager->getRepository(CompanySettings::class)->findSingleton();
            self::assertInstanceOf(CompanySettings::class, $company);
            self::assertSame('Société Test SARL', $company->getLegalName());
            self::assertSame('Société Test', $company->getTradeName());
            self::assertSame('1234567/A/M/000', $company->getTaxId());
            self::assertSame('contact@societe-test.tn', $company->getEmail());
            self::assertSame('+216 71 000 000', $company->getPhone());
            self::assertSame('12 rue du Test', $company->getAddress());
            self::assertSame('12 rue du Test, 1000 Tunis, Tunisie', $company->getFormattedAddress());
            self::assertTrue($company->hasLogo());

            $this->client->request('GET', '/company/logo');
            self::assertResponseIsSuccessful();
            self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
            $response = $this->client->getResponse();
            self::assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
            self::assertStringStartsWith("\x89PNG", (string) file_get_contents($response->getFile()->getPathname()));
        } finally {
            $storage = static::getContainer()->get(DocumentStorage::class);
            $company = $this->entityManager->getRepository(CompanySettings::class)->findSingleton();
            if (null !== $company && null !== $company->getLogoPath()) {
                $storage->delete($company->getLogoPath());
            }
            @unlink($logoPath);
        }
    }

    public function testMailSettingsEncryptThePasswordAndKeepItWhenTheFieldIsBlank(): void
    {
        $user = $this->createUser('ROLE_SETTINGS_MAIL', ['admin.settings']);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/admin/parametres/email');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['mail_settings_form[host]']->setValue('smtp.societe-test.tn');
        $form['mail_settings_form[port]']->setValue('587');
        $form['mail_settings_form[encryption]']->setValue('starttls');
        $form['mail_settings_form[username]']->setValue('rappels@societe-test.tn');
        $form['mail_settings_form[password]']->setValue('Secret-SMTP-42!');
        $form['mail_settings_form[senderEmail]']->setValue('rappels@societe-test.tn');
        $form['mail_settings_form[senderName]']->setValue('Société Test');
        $form['mail_settings_form[isEnabled]']->tick();

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/parametres/email');

        $this->entityManager->clear();
        $mail = $this->entityManager->getRepository(MailSettings::class)->findSingleton();
        self::assertInstanceOf(MailSettings::class, $mail);
        self::assertTrue($mail->isEnabled());
        self::assertSame('smtp.societe-test.tn', $mail->getHost());
        self::assertSame(587, $mail->getPort());
        self::assertTrue($mail->hasPassword());
        self::assertNotSame('Secret-SMTP-42!', $mail->getPasswordEncrypted());

        $cipher = static::getContainer()->get(SecretCipher::class);
        self::assertSame('Secret-SMTP-42!', $cipher->decrypt($mail->getPasswordEncrypted()));
        $encrypted = $mail->getPasswordEncrypted();

        $crawler = $this->client->request('GET', '/admin/parametres/email');
        self::assertResponseIsSuccessful();
        self::assertContains($crawler->filter('input[name="mail_settings_form[password]"]')->attr('value'), [null, '']);

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['mail_settings_form[host]']->setValue('smtp2.societe-test.tn');
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/parametres/email');

        $this->entityManager->clear();
        $mail = $this->entityManager->getRepository(MailSettings::class)->findSingleton();
        self::assertInstanceOf(MailSettings::class, $mail);
        self::assertSame('smtp2.societe-test.tn', $mail->getHost());
        self::assertSame($encrypted, $mail->getPasswordEncrypted());
    }

    public function testTestEmailRejectsAnInvalidRecipient(): void
    {
        $user = $this->createUser('ROLE_SETTINGS_TEST_MAIL', ['admin.settings']);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/admin/parametres/email');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/parametres/email/test', [
            '_token' => $token,
            'test_email' => 'pas-une-adresse',
        ]);

        self::assertResponseRedirects('/admin/parametres/email');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--error', 'adresse électronique valide');
    }

    public function testMailDsnReflectsTheEncryptionMode(): void
    {
        $mailer = static::getContainer()->get(CompanyMailer::class);
        $cipher = static::getContainer()->get(SecretCipher::class);

        $settings = (new MailSettings())
            ->setHost('smtp.societe-test.tn')
            ->setPort(1025)
            ->setEncryption(MailEncryption::None)
            ->setUsername('rappels@societe-test.tn')
            ->setPasswordEncrypted($cipher->encrypt('p@ss'));
        self::assertSame(
            'smtp://rappels%40societe-test.tn:p%40ss@smtp.societe-test.tn:1025?auto_tls=false',
            $mailer->dsn($settings),
        );

        $settings->setEncryption(MailEncryption::StartTls)->setPort(587);
        self::assertSame(
            'smtp://rappels%40societe-test.tn:p%40ss@smtp.societe-test.tn:587?require_tls=true',
            $mailer->dsn($settings),
        );

        $settings->setEncryption(MailEncryption::Tls)->setPort(465);
        self::assertSame(
            'smtps://rappels%40societe-test.tn:p%40ss@smtp.societe-test.tn:465',
            $mailer->dsn($settings),
        );
    }

    public function testSendRemindersCommandFailsWhenMailIsDisabled(): void
    {
        $application = new FrameworkApplication(static::$kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('app:contracts:send-reminders'));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('désactivé', $tester->getDisplay());
    }

    public function testSendRemindersActionRefusesWhenMailIsDisabled(): void
    {
        $user = $this->createUser('ROLE_REMINDER_SEND', ['contract.notify']);
        $this->client->loginUser($user);

        $url = static::getContainer()->get('router')->generate('app_contract_notification_send_reminders');
        $indexUrl = static::getContainer()->get('router')->generate('app_contract_notification_index');
        $crawler = $this->client->request('GET', $indexUrl);
        self::assertResponseIsSuccessful();
        $token = $crawler->filter(sprintf('form[action="%s"] input[name="_token"]', $url))->attr('value');

        $this->client->request('POST', $url, ['_token' => $token]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--error', 'désactivé');
    }

    public function testContractReminderEmailTemplatesRender(): void
    {
        $user = $this->createUser('ROLE_REMINDER_TEMPLATE', ['contract.notify']);
        $client = $this->createClientFixture('Client rappel');
        $contract = $this->createContractFixture($client, $user);

        $notification = (new ContractNotification())
            ->setContract($contract)
            ->setNotificationType(ContractNotificationType::ContractExpiration)
            ->setEventDate(new \DateTimeImmutable('2026-11-30'))
            ->setTriggerDate(new \DateTimeImmutable('2026-10-01'))
            ->setAlertDelayDays(60)
            ->setSeverity(ContractNotificationSeverity::Warning)
            ->setRecipient($user)
            ->setUniqueKey('test-'.uniqid('', true))
            ->setTitle('Contrat arrivant à échéance')
            ->setMessage('Le contrat arrive à échéance dans 60 jours.');

        $company = new CompanySettings();
        $context = [
            'notification' => $notification,
            'contract' => $contract,
            'recipient' => $user,
            'company' => $company,
            'contract_url' => 'http://localhost:8090/contrats/'.$contract->getIdString(),
            'logo_url' => null,
        ];

        $twig = static::getContainer()->get(Environment::class);
        $html = $twig->render('emails/contract_reminder.html.twig', $context);
        $text = $twig->render('emails/contract_reminder.txt.twig', $context);
        $testHtml = $twig->render('emails/test.html.twig', [
            'company' => $company,
            'settings' => (new MailSettings())
                ->setHost('smtp.example.tn')
                ->setPort(587)
                ->setSenderEmail('no-reply@example.tn')
                ->setSenderName('Société Test'),
        ]);

        self::assertStringContainsString($contract->getReference(), $html);
        self::assertStringContainsString('Consulter le contrat', $html);
        self::assertStringContainsString($contract->getReference(), $text);
        self::assertStringContainsString('Test de configuration email', $testHtml);
    }
}