<?php

namespace App\Command\Mail;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Envoie un email de test a une adresse specifique.'
)]
class SendTestMailCommand extends Command
{
    public function __construct(private MailerInterface $mailer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse destinataire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $to = trim((string) $input->getArgument('to'));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse email destinataire invalide.');
            return Command::INVALID;
        }

        $from = (string) (getenv('MAILER_FROM') ?: ($_ENV['MAILER_FROM'] ?? 'no-reply@eventflow.local'));

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->to($to)
                    ->subject('EventFlow - Test Mailer Symfony')
                    ->text("Ceci est un email de test Symfony Mailer.\nFrom: {$from}\nTo: {$to}")
                    ->html('<p>Ceci est un email de test <strong>Symfony Mailer</strong>.</p><p>From: ' . htmlspecialchars($from, ENT_QUOTES) . '<br>To: ' . htmlspecialchars($to, ENT_QUOTES) . '</p>')
            );
        } catch (\Throwable $exception) {
            $io->error('Echec envoi SMTP: ' . $exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('Email de test envoye a ' . $to);
        return Command::SUCCESS;
    }
}
