<?php

namespace App\Command;

use App\Entity\Event\Event;
use App\Repository\Event\EventRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:list-events')]
class ListEventsCommand extends Command
{
    private EventRepository $eventRepository;

    public function __construct(EventRepository $eventRepository)
    {
        parent::__construct();
        $this->eventRepository = $eventRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Liste des événements');

        $events = $this->eventRepository->findAll();
        $total = count($events);

        if ($total === 0) {
            $io->error('Aucun événement trouvé dans la base de données.');
            return Command::FAILURE;
        }

        $io->text("Total des événements: {$total}");

        $tableRows = [];

        foreach ($events as $event) {
            $questionCount = $event->getQuestions()->count();
            $tableRows[] = [
                $event->getId(),
                $event->getTitle(),
                $questionCount,
                $questionCount > 0 ? '✓' : '✗',
            ];
        }

        $io->table(
            ['ID', 'Titre', 'Nombre de questions', 'Quiz disponible'],
            $tableRows
        );

        return Command::SUCCESS;
    }
}
