<?php

namespace App\Command\Sponsor;

use App\Service\Sponsor\SponsorAlertEmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sponsor:send-recommendation-emails',
    description: 'Envoie des emails automatiques aux sponsors quand des evenements recommandes sont disponibles.'
)]
class SendSponsorAlertEmailsCommand extends Command
{
    public function __construct(private SponsorAlertEmailService $sponsorAlertEmailService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Envoi des recommandations sponsor par email');

        $mailerDsn = (string) (getenv('MAILER_DSN') ?: ($_ENV['MAILER_DSN'] ?? ''));
        if ($mailerDsn === '' || str_starts_with($mailerDsn, 'null://')) {
            $io->warning('MAILER_DSN est en mode null://null: aucun email reel ne sera livre. Configurez un SMTP dans .env.local.');
        }

        $stats = $this->sponsorAlertEmailService->sendRecommendedEventsDigestForAllSponsors();

        $io->definitionList(
            ['Sponsors analyses' => (string) $stats['checked']],
            ['Sponsors avec recommandations' => (string) $stats['withRecommendations']],
            ['Emails envoyes' => (string) $stats['sent']],
            ['Erreurs' => (string) $stats['errors']]
        );

        if ((int) $stats['errors'] > 0) {
            $io->warning('Certaines notifications n\'ont pas pu etre envoyees.');
            if (!empty($stats['lastError'])) {
                $io->writeln('Derniere erreur SMTP: ' . (string) $stats['lastError']);
            }
            return Command::FAILURE;
        }

        $io->success('Traitement termine.');
        return Command::SUCCESS;
    }
}
