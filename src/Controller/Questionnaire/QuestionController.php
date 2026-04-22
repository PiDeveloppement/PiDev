<?php

namespace App\Controller\Questionnaire;

use App\Entity\Questionnaire\Question;
use App\Entity\Event\Event;
use App\Form\Questionnaire\QuestionType;
use App\Repository\Questionnaire\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Questionnaire\QuestionGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
// Pour le PDF
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/questionnaire')]
class QuestionController extends AbstractController
{
    // ------------------------------------------------------------------ INDEX
    #[Route('/', name: 'app_question_index', methods: ['GET'])]
    public function index(QuestionRepository $questionRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $eventId = $request->query->get('event');
        
        if ($eventId) {
            $event = $entityManager->getRepository(Event::class)->find($eventId);
            if (!$event) {
                $this->addFlash('error', 'Événement non trouvé.');
                return $this->redirectToRoute('app_question_index');
            }
            $questions = $questionRepository->createQueryBuilder('q')
                ->where('q.event = :event')
                ->setParameter('event', $event)
                ->orderBy('q.points', 'DESC')
                ->getQuery()
                ->getResult();
            $events = $entityManager->getRepository(Event::class)->findAll();
        } else {
            $questions = $questionRepository->createQueryBuilder('q')
                ->orderBy('q.points', 'DESC')
                ->getQuery()
                ->getResult();
            $events = $entityManager->getRepository(Event::class)->findAll();
        }
        
        return $this->render('questionnaire/question/index.html.twig', [
            'questions' => $questions,
            'events' => $events,
            'currentEvent' => $eventId,
        ]);
    }

    // ------------------------------------------------------------------ NEW
    #[Route('/new', name: 'app_question_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $question = new Question();
        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($question);
            $entityManager->flush();
            $this->addFlash('success', 'Question créée avec succès.');
            return $this->redirectToRoute('app_question_index');
        }

        return $this->render('questionnaire/question/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ------------------------------------------------------------------ EDIT
    #[Route('/{id}/edit', name: 'app_question_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Question $question, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Question mise à jour.');
            return $this->redirectToRoute('app_question_index');
        }

        return $this->render('questionnaire/question/edit.html.twig', [
            'question' => $question,
            'form'     => $form->createView(),
        ]);
    }

    // ------------------------------------------------------------------ DELETE
    #[Route('/{id}/delete', name: 'app_question_delete', methods: ['POST'])]
    public function delete(Request $request, Question $question, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $question->getId(), $request->request->get('_token'))) {
            $entityManager->remove($question);
            $entityManager->flush();
            $this->addFlash('success', 'Question supprimée avec succès.');
        }

        return $this->redirectToRoute('app_question_index');
    }

    // ------------------------------------------------------------------ STATS
    #[Route('/stats', name: 'app_question_stats', methods: ['GET'])]
    public function stats(QuestionRepository $questionRepository): Response
    {
        $questions = $questionRepository->findAll();

        $total     = count($questions);
        $sumPoints = 0;
        $maxPoints = 0;
        $withEvent = 0;
        $byEvent   = [];

        foreach ($questions as $q) {
            $pts = $q->getPoints() ?? 0;
            $sumPoints += $pts;
            if ($pts > $maxPoints) $maxPoints = $pts;

            if ($q->getEvent()) {
                $withEvent++;
                $evTitle = $q->getEvent()->getTitle();
                $byEvent[$evTitle] = ($byEvent[$evTitle] ?? 0) + 1;
            }
        }

        arsort($byEvent); // sort by count desc

        $maxByEvent = $byEvent ? max(array_values($byEvent)) : 1;

        // Top 10 questions by points
        $sorted = $questions;
        usort($sorted, fn($a, $b) => ($b->getPoints() ?? 0) - ($a->getPoints() ?? 0));
        $topQuestions = array_slice($sorted, 0, 10);

        $stats = [
            'total'        => $total,
            'avgPoints'    => $total > 0 ? $sumPoints / $total : 0,
            'maxPoints'    => $maxPoints,
            'withEvent'    => $withEvent,
            'byEvent'      => $byEvent,
            'maxByEvent'   => $maxByEvent,
            'topQuestions' => $topQuestions,
        ];

        return $this->render('questionnaire/question/stats.html.twig', [
            'stats' => $stats,
        ]);
    }

    // ------------------------------------------------------------------ PDF EXPORT
    #[Route('/pdf', name: 'app_question_pdf', methods: ['GET'])]
    public function exportPdf(QuestionRepository $questionRepository): Response
    {
        $questions = $questionRepository->findAll();

        $total     = count($questions);
        $sumPoints = 0;
        $maxPoints = 0;
        $withEvent = 0;
        $byEvent   = [];

        foreach ($questions as $q) {
            $pts = $q->getPoints() ?? 0;
            $sumPoints += $pts;
            if ($pts > $maxPoints) $maxPoints = $pts;
            if ($q->getEvent()) {
                $withEvent++;
                $evTitle = $q->getEvent()->getTitle();
                $byEvent[$evTitle] = ($byEvent[$evTitle] ?? 0) + 1;
            }
        }
        arsort($byEvent);

        $sorted = $questions;
        usort($sorted, fn($a, $b) => ($b->getPoints() ?? 0) - ($a->getPoints() ?? 0));
        $topQuestions = array_slice($sorted, 0, 10);

        $avgPoints = $total > 0 ? round($sumPoints / $total, 1) : 0;
        $date      = (new \DateTime())->format('d/m/Y H:i');

        $stats = [
            'total'     => $total,
            'avgPoints' => $avgPoints,
            'maxPoints' => $maxPoints,
            'withEvent' => $withEvent,
            'byEvent'   => $byEvent,
            'topQuestions' => $topQuestions,
        ];

        // Generate HTML from template
        $html = $this->renderView('questionnaire/question/pdf_stats.html.twig', [
            'stats' => $stats,
            'questions' => $questions,
            'date' => $date,
        ]);

        // Generate PDF with Dompdf
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'questions_stats_' . date('Ymd_His') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
    #[Route('/generate-ai', name: 'app_question_generate_ai', methods: ['POST'])]
public function generateAI(Request $request, QuestionGenerator $generator, EntityManagerInterface $entityManager): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $eventId = $data['eventId'] ?? null;
    
    try {
        if ($eventId) {
            // Générer basé sur un événement spécifique
            $event = $entityManager->getRepository(Event::class)->find($eventId);
            if (!$event) {
                return new JsonResponse(['error' => 'Événement non trouvé'], 404);
            }
            $result = $generator->generateFromEvent($event);
        } else {
            // Générer basé sur une description générale
            $description = $data['description'] ?? 'Quiz général';
            $result = $generator->generate($description);
        }
        
        return new JsonResponse($result);
    } catch (\Exception $e) {
        return new JsonResponse(['error' => $e->getMessage()], 500);
    }
}
}