<?php

namespace App\Command;

use App\Entity\Questionnaire\Question;
use App\Repository\Questionnaire\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:check-question-event-associations')]
class CheckQuestionEventAssociationsCommand extends Command
{
    private QuestionRepository $questionRepository;

    public function __construct(QuestionRepository $questionRepository)
    {
        parent::__construct();
        $this->questionRepository = $questionRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Associations Questions-Événements');

        $questions = $this->questionRepository->findAll();
        $total = count($questions);

        if ($total === 0) {
            $io->error('Aucune question trouvée dans la base de données.');
            return Command::FAILURE;
        }

        $io->text("Total des questions: {$total}");

        $io->section('Liste des questions et leurs événements');

        $tableRows = [];
        $withEvent = 0;
        $withoutEvent = 0;

        foreach ($questions as $question) {
            $eventTitle = $question->getEvent() ? $question->getEvent()->getTitle() : 'Aucun';
            $eventId = $question->getEvent() ? $question->getEvent()->getId() : 'N/A';
            
            if ($question->getEvent()) {
                $withEvent++;
            } else {
                $withoutEvent++;
            }

            $tableRows[] = [
                $question->getId(),
                substr($question->getTexte(), 0, 50) . (strlen($question->getTexte()) > 50 ? '...' : ''),
                $eventTitle,
                $eventId,
            ];
        }

        $io->table(
            ['ID', 'Question', 'Événement', 'ID Événement'],
            $tableRows
        );

        $io->section('Résumé');
        $io->text("Questions avec événement: {$withEvent}");
        $io->text("Questions sans événement: {$withoutEvent}");

        if ($withoutEvent > 0) {
            $io->warning("{$withoutEvent} questions ne sont associées à aucun événement.");
        }

        return Command::SUCCESS;
    }
}
