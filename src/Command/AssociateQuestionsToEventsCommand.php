<?php

namespace App\Command;

use App\Entity\Event\Event;
use App\Entity\Questionnaire\Question;
use App\Repository\Event\EventRepository;
use App\Repository\Questionnaire\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:associate-questions-to-events')]
class AssociateQuestionsToEventsCommand extends Command
{
    private EntityManagerInterface $em;
    private QuestionRepository $questionRepository;
    private EventRepository $eventRepository;

    public function __construct(EntityManagerInterface $em, QuestionRepository $questionRepository, EventRepository $eventRepository)
    {
        parent::__construct();
        $this->em = $em;
        $this->questionRepository = $questionRepository;
        $this->eventRepository = $eventRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Association des questions aux événements');

        // Récupérer toutes les questions sans événement
        $questionsWithoutEvent = $this->questionRepository->createQueryBuilder('q')
            ->where('q.event IS NULL')
            ->getQuery()
            ->getResult();

        $count = count($questionsWithoutEvent);

        if ($count === 0) {
            $io->success('Toutes les questions sont déjà associées à des événements.');
            return Command::SUCCESS;
        }

        $io->text("Found {$count} questions without events.");

        // Récupérer tous les événements
        $events = $this->eventRepository->findAll();
        $eventCount = count($events);

        if ($eventCount === 0) {
            $io->error('No events found in the database. Please create events first.');
            return Command::FAILURE;
        }

        $io->text("Found {$eventCount} events available.");

        // Associer chaque question sans événement à un événement aléatoire
        $updated = 0;
        foreach ($questionsWithoutEvent as $question) {
            $randomEvent = $events[array_rand($events)];
            $question->setEvent($randomEvent);
            $this->em->persist($question);
            $updated++;
        }

        $this->em->flush();

        $io->success("Successfully associated {$updated} questions to events.");

        return Command::SUCCESS;
    }
}
