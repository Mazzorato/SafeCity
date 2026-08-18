<?php

namespace App\Command;

use App\Service\EventReminderSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Déclenche les rappels d’événements via le planificateur Symfony local.
 */
#[AsCommand(
    name: 'app:send-event-reminders',
    description: 'Envoie les rappels des événements favoris commençant dans les prochaines 24 heures.'
)]
#[AsCronTask('*/15 * * * *')]
final class SendEventRemindersCommand extends Command
{
    public function __construct(
        private EventReminderSender $reminderSender,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sentCount = $this->reminderSender->sendDue();
        $output->writeln(sprintf(
            '%d rappel%s d’événement envoyé%s.',
            $sentCount,
            $sentCount > 1 ? 's' : '',
            $sentCount > 1 ? 's' : '',
        ));

        return Command::SUCCESS;
    }
}
