<?php

namespace App\Controller\Questionnaire;

use App\Entity\Questionnaire\QuizSession;
use App\Entity\Questionnaire\Question;
use App\Entity\Event\Event;
use App\Repository\Questionnaire\QuizSessionRepository;
use App\Repository\Questionnaire\QuestionRepository;
use App\Repository\Event\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuizWithRecaptchaController extends AbstractController
{
    private QuizSessionRepository $sessionRepository;
    private QuestionRepository $questionRepository;
    private EventRepository $eventRepository;
    private EntityManagerInterface $em;

    public function __construct(
        QuizSessionRepository $sessionRepository,
        QuestionRepository $questionRepository,
        EventRepository $eventRepository,
        EntityManagerInterface $em
    ) {
        $this->sessionRepository = $sessionRepository;
        $this->questionRepository = $questionRepository;
        $this->eventRepository = $eventRepository;
        $this->em = $em;
    }

    /**
     * Page d'accueil du quiz avec reCAPTCHA
     */
    #[Route('/quiz/start/{eventId?}', name: 'app_quiz_start_recaptcha')]
    public function startQuizWithRecaptcha(?int $eventId = null): Response
    {
        $event = null;
        if ($eventId) {
            $event = $this->eventRepository->find($eventId);
            if (!$event) {
                throw $this->createNotFoundException('Événement non trouvé');
            }
        }

        return $this->render('questionnaire/quiz/start_with_recaptcha.html.twig', [
            'event' => $event,
            'event_id' => $eventId,
            'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'] ?? ''
        ]);
    }

    /**
     * Page pour passer le quiz
     */
    #[Route('/quiz/take/{token}', name: 'app_quiz_take')]
    public function takeQuiz(string $token, Request $request): Response
    {
        $session = $this->sessionRepository->findBySessionToken($token);
        
        if (!$session) {
            throw $this->createNotFoundException('Session de quiz invalide');
        }

        if (!$session->isRecaptchaVerified() || $session->getStatus() !== 'started') {
            $this->addFlash('error', 'Veuillez d\'abord compléter la vérification reCAPTCHA');
            return $this->redirectToRoute('app_quiz_start_recaptcha');
        }

        if ($session->isExpired()) {
            $session->setStatus('aborted');
            $this->em->flush();
            $this->addFlash('error', 'La session de quiz a expiré');
            return $this->redirectToRoute('app_quiz_start_recaptcha');
        }

        // Récupérer les questions
        $questions = $this->questionRepository->findQuestionsForQuiz($session->getEvent());
        
        if (empty($questions)) {
            $this->addFlash('error', 'Aucune question disponible pour ce quiz');
            return $this->redirectToRoute('app_quiz_start_recaptcha');
        }

        // Mélanger les options pour chaque question
        foreach ($questions as $question) {
            $options = [
                $question->getReponse(),
                $question->getOption1(),
                $question->getOption2(),
                $question->getOption3()
            ];
            // Filtrer les options vides et mélanger
            $options = array_filter($options, function($option) {
                return !empty($option);
            });
            shuffle($options);
            $question->shuffledOptions = $options;
        }

        $currentQuestionIndex = (int) $request->query->get('question', 0);
        $currentQuestionIndex = max(0, min($currentQuestionIndex, count($questions) - 1));

        // Récupérer les réponses sauvegardées (si disponible)
        $answers = [];
        // TODO: Implémenter la récupération des réponses depuis la session ou la base de données

        return $this->render('questionnaire/quiz/take_quiz.html.twig', [
            'sessionToken' => $token,
            'questions' => $questions,
            'question' => $questions[$currentQuestionIndex],
            'currentQuestion' => $currentQuestionIndex,
            'totalQuestions' => count($questions),
            'shuffledOptions' => $questions[$currentQuestionIndex]->shuffledOptions,
            'answers' => $answers,
            'currentPoints' => 0 // TODO: Calculer les points actuels
        ]);
    }

    /**
     * Page de résultats du quiz
     */
    #[Route('/quiz/results/{token}', name: 'app_quiz_results')]
    public function quizResults(string $token): Response
    {
        $session = $this->sessionRepository->findBySessionToken($token);
        
        if (!$session || $session->getStatus() !== 'completed') {
            throw $this->createNotFoundException('Résultats non disponibles');
        }

        // TODO: Implémenter la logique des résultats
        $score = 0;
        $totalPoints = 0;
        $percentage = 0;

        return $this->render('questionnaire/quiz/results.html.twig', [
            'session' => $session,
            'score' => $score,
            'totalPoints' => $totalPoints,
            'percentage' => $percentage
        ]);
    }
}
