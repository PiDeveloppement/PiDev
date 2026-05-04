<?php

namespace App\Command\Sms;

use App\Bundle\NotificationBundle\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:sms:test')]
class SendTestSmsCommand extends Command
{
    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Send a test SMS via Infobip')
            ->addArgument('phone', InputArgument::REQUIRED, 'Phone number to send SMS to')
            ->addArgument('message', InputArgument::OPTIONAL, 'Message to send', 'Test SMS from EventFlow');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $phone = $input->getArgument('phone');
        $message = $input->getArgument('message');

        $output->writeln('<info>=== Test SMS Infobip ===</info>');
        $output->writeln('<comment>Phone: ' . $phone . '</comment>');
        $output->writeln('<comment>Message: ' . $message . '</comment>');

        try {
            $this->notificationService->sendSms($phone, $message);
            $output->writeln('<info>✅ SMS envoyé avec succès!</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>❌ Erreur envoi SMS: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>Stack trace:</error>');
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
