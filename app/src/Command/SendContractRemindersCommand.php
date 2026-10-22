<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AuditLogger;
use App\Service\ContractNotificationService;
use App\Service\Mail\ContractReminderMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Génère les alertes contractuelles dues et envoie les rappels par email.
 *
 * À planifier (cron) après avoir activé et testé les paramètres email.
 */
#[AsCommand(
    name: 'app:contracts:send-reminders',
    description: 'Génère les alertes contractuelles et envoie les rappels par email.',
)]
final class SendContractRemindersCommand extends Command
{
    public function __construct(
        private readonly ContractNotificationService $notificationService,
        private readonly ContractReminderMailer $reminderMailer,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_REQUIRED,
            'Nombre maximal de rappels envoyés par exécution.',
            '100',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, min(500, (int) $input->getOption('limit')));

        $created = $this->notificationService->generateAll();
        $result = $this->reminderMailer->sendPending($limit);

        if (isset($result['reason'])) {
            $io->warning($result['reason']);

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%d alerte(s) générée(s), %d rappel(s) envoyé(s), %d échec(s), %d ignoré(s).',
            $created,
            $result['sent'],
            $result['failed'],
            $result['skipped'],
        ));

        $this->auditLogger->log('send_reminders', 'ContractNotification', null, null, [
            'created' => $created,
            'sent' => $result['sent'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
        ], 'success', 'M15', 'Envoi automatique des rappels contractuels');

        return Command::SUCCESS;
    }
}