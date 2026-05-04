<?php

namespace App\Controller\Questionnaire;

use App\Entity\Questionnaire\Question;
use App\Entity\Questionnaire\Feedback;
use App\Entity\Questionnaire\QuizAnswer;
use App\Entity\Questionnaire\QuizSession;
use App\Entity\User\UserModel;
use App\Entity\Event\Event;
use App\Form\Questionnaire\FeedbackType;
use App\Form\Questionnaire\QuizAnswerType;
use App\Service\Questionnaire\ContentModerationService;
use App\Repository\Questionnaire\QuestionRepository;
use App\Repository\Questionnaire\QuizSessionRepository;
use App\Repository\Event\EventRepository;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Google\Service\AlertCenter\User;

class QuizController extends AbstractController
{
    private UserRepository $userRepository;
    private EventRepository $eventRepository;
    private ContentModerationService $moderationService;

    public function __construct(EventRepository $eventRepository, UserRepository $userRepository, ContentModerationService $moderationService)
    {
        $this->eventRepository = $eventRepository;
        $this->userRepository = $userRepository;
        $this->moderationService = $moderationService;
        // Configurer le service de modération pour l'entité Feedback
        Feedback::setModerationService($moderationService);
    }

    #[Route('/quiz/start', name: 'app_quiz_start')]
    public function start(Request $request, QuestionRepository $repo, UserRepository $user): Response
    {
        $user = $this->getUser();
        $eventId = $request->query->get('event_id');
        
        if (!$user) {
            $session = $request->getSession();
            $session->set('pending_quiz_event', $eventId);
            $session->set('after_login_redirect', 'app_quiz_start');
            $this->addFlash('error', 'Vous devez être connecté pour passer le quiz.');
            return $this->redirectToRoute('app_login');
        }
        
        $allowedRoleIds = [1, 5];
        if (!in_array($user->getRoleId(), $allowedRoleIds)) {
            $this->addFlash('error', 'Vous n\'avez pas les droits pour passer ce quiz.');
            return $this->redirectToRoute('app_public_events');
        }
        
        if ($eventId) {
            $event = $this->eventRepository->find($eventId);
            if (!$event) {
                $this->addFlash('error', 'Événement non trouvé.');
                return $this->redirectToRoute('app_public_events');
            }
            
            $questions = $repo->createQueryBuilder('q')
                ->where('q.event = :eventId')
                ->setParameter('eventId', $eventId)
                ->getQuery()
                ->getResult();
                
            if (empty($questions)) {
                $this->addFlash('error', 'Aucune question disponible pour cet événement.');
                return $this->redirectToRoute('app_public_events');
            }
        } else {
            $this->addFlash('error', 'Veuillez sélectionner un événement pour passer le quiz.');
            return $this->redirectToRoute('app_public_events');
        }
        
        shuffle($questions);
        $questions = array_slice($questions, 0, 10);

        $session = $this->container->get('request_stack')->getSession();
        $session->set('quiz_questions', $questions);
        $session->set('quiz_answers', []);
        $session->set('quiz_event_id', $eventId);

        return $this->render('questionnaire/quiz/index.html.twig', [
            'questions' => $questions,
            'eventId' => $eventId,
            'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'] ?? ''
        ]);
    }

    #[Route('/quiz/validate-answer', name: 'app_quiz_validate_answer', methods: ['POST'])]
    public function validateAnswer(Request $request, ValidatorInterface $validator): Response
    {
        $session = $request->getSession();
        $questionId = $request->request->get('questionId');
        $answer = $request->request->get('answer');

        $quizAnswer = new QuizAnswer();
        $quizAnswer->setQuestionId($questionId);
        $quizAnswer->setAnswer($answer);

        $errors = $validator->validate($quizAnswer);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            
            return $this->json([
                'success' => false,
                'errors' => $errorMessages
            ]);
        }

        $answers = $session->get('quiz_answers', []);
        $answers[$questionId] = $answer;
        $session->set('quiz_answers', $answers);

        return $this->json([
            'success' => true
        ]);
    }

    #[Route('/quiz/submit', name: 'app_quiz_submit', methods: ['POST'])]
    public function submit(Request $request, QuestionRepository $repo, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $data = $session->get('quiz_answers', []); 
        $evaluation = $request->request->all('evaluation'); 
        $timeouts = $request->request->all('timeout'); // Questions non répondues par timeout
        
        $results = [];
        $score = 0;
        $quizQuestions = $session->get('quiz_questions', []);
        
        // Récupérer les entités Question fraîches depuis la base de données
        $questionIds = array_map(function($q) { return $q->getId(); }, $quizQuestions);
        $freshQuestions = $repo->findBy(['id' => $questionIds]);
        
        // Traiter uniquement les 10 questions sélectionnées pour le quiz
        foreach ($freshQuestions as $question) {
            $questionId = $question->getId();
            $isTimeout = isset($timeouts[$questionId]);
            $hasAnswer = isset($data[$questionId]);
            
            // Créer un feedback pour chaque question
            $fb = new Feedback();
            $fb->setQuestion($question);
            
            $user = $this->getUser();
            if ($user) {
                $fb->setUserId($user->getId());
                $fb->setUser($user);
            } else {
                $fb->setUserId(null);
            }
            
            if ($isTimeout) {
                // Question non répondue (timeout) = FAUX automatique
                $fb->setReponseDonnee("Non répondu (temps écoulé)");
                $fb->setComments("Question non répondue - temps écoulé (20 secondes)");
                $fb->setEtoiles(0);
                $isCorrect = false;
                
                $results[] = [
                    'question' => $question->getTexte(),
                    'userAnswer' => 'Non répondu (temps écoulé)',
                    'correctAnswer' => $question->getReponse(),
                    'isCorrect' => false,
                    'timeout' => true
                ];
            } elseif ($hasAnswer) {
                // Question répondue normalement
                $userAnswer = $data[$questionId];
                $isCorrect = (trim(strtolower($question->getReponse())) === trim(strtolower($userAnswer)));
                if ($isCorrect) $score++;
                
                $fb->setReponseDonnee($userAnswer);
                $fb->setComments("Réponse utilisateur");
                $fb->setEtoiles(0);
                
                $results[] = [
                    'question' => $question->getTexte(),
                    'userAnswer' => $userAnswer,
                    'correctAnswer' => $question->getReponse(),
                    'isCorrect' => $isCorrect,
                    'timeout' => false
                ];
            } else {
                // Question non répondue (pas de timeout) = FAUX
                $fb->setReponseDonnee("Non répondu");
                $fb->setComments("Question non répondue");
                $fb->setEtoiles(0);
                $isCorrect = false;
                
                $results[] = [
                    'question' => $question->getTexte(),
                    'userAnswer' => 'Non répondu',
                    'correctAnswer' => $question->getReponse(),
                    'isCorrect' => false,
                    'timeout' => false
                ];
            }
            
            $em->persist($fb);
        }
        
        $em->flush();
        
        $userFeedback = null;
        if (isset($evaluation['comments']) && isset($evaluation['etoiles'])) {
            $userFeedback = new Feedback();
            
            // Modérer le commentaire avant de le sauvegarder
            $moderatedComment = $this->moderationService->moderateContent($evaluation['comments']);
            $userFeedback->setComments($moderatedComment);
            $userFeedback->setEtoiles((int)$evaluation['etoiles']);
            
            $user = $this->getUser();
            if ($user) {
                $userFeedback->setUserId($user->getId());
                $userFeedback->setUser($user);
            } else {
                $userFeedback->setUserId(null);
            }
            
            $em->persist($userFeedback);
            $em->flush();
        }
        
        $session->set('quiz_results', [
            'results' => $results,
            'score' => $score,
            'total' => count($results) ?: 1,
            'userFeedback' => $userFeedback
        ]);

        return $this->redirectToRoute('app_quiz_final_results');
    }

    #[Route('/quiz/results', name: 'app_quiz_results')]
    public function results(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $quizResults = $session->get('quiz_results');

        $feedbackRepository = $em->getRepository(Feedback::class);
        $user = $this->getUser();
        $existingFeedbacks = [];
        if ($user) {
            $existingFeedbacks = $feedbackRepository->findBy(['userId' => $user->getId()], ['createdAt' => 'DESC']);
        }

        return $this->render('questionnaire/quiz/results.html.twig', [
            'results' => $quizResults['results'],
            'score' => $quizResults['score'],
            'total' => $quizResults['total'],
            'existingFeedbacks' => $existingFeedbacks
        ]);
    }

    #[Route('/quiz/final-results', name: 'app_quiz_final_results')]
    public function finalResults(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $quizResults = $session->get('quiz_results');

        if (!$quizResults) {
            return $this->redirectToRoute('app_quiz_start');
        }

        return $this->render('questionnaire/quiz/final_results.html.twig', [
            'results' => $quizResults['results'],
            'score' => $quizResults['score'],
            'total' => $quizResults['total'],
            'userFeedback' => $quizResults['userFeedback']
        ]);
    }

    #[Route('/quiz/feedback/edit/{id}', name: 'app_quiz_feedback_edit', methods: ['POST'])]
    public function editFeedback(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $feedback = $em->getRepository(Feedback::class)->find($id);
        
        if (!$feedback) {
            $this->addFlash('error', 'Feedback non trouvé');
            return $this->redirectToRoute('app_quiz_final_results');
        }
        
        $user = $this->getUser();
        if (!$user || $feedback->getUserId() !== $user->getId()) {
            $this->addFlash('error', 'Non autorisé');
            return $this->redirectToRoute('app_quiz_final_results');
        }
        
        $etoiles = $request->request->get('etoiles');
        $comments = $request->request->get('comments');
        
        if ($etoiles !== null) {
            // Modérer le commentaire avant de le sauvegarder
            if ($comments) {
                $moderatedComment = $this->moderationService->moderateContent($comments);
                $feedback->setComments($moderatedComment);
            }
            
            $feedback->setEtoiles((int)$etoiles);
            $em->flush();
            
            // Mettre à jour la session avec le feedback modifié
            $session = $request->getSession();
            $quizResults = $session->get('quiz_results');
            if ($quizResults && isset($quizResults['userFeedback']) && $quizResults['userFeedback']->getId() === $feedback->getId()) {
                $quizResults['userFeedback'] = $feedback;
                $session->set('quiz_results', $quizResults);
            }
            
            $this->addFlash('success', 'Votre avis a été modifié avec succès');
            return $this->redirectToRoute('app_quiz_final_results');
        }
        
        $this->addFlash('error', 'Données invalides');
        return $this->redirectToRoute('app_quiz_final_results');
    }

    #[Route('/quiz/feedback/save', name: 'app_quiz_feedback_save', methods: ['POST'])]
    public function saveFeedback(Request $request, EntityManagerInterface $em): Response
    {
        $feedback = new Feedback();
        
        $form = $this->createFormBuilder($feedback)
            ->add('etoiles', IntegerType::class, [
                'label' => 'Note (0 à 5)',
                'attr' => [
                    'class' => 'form-control text-center',
                    'min' => 0,
                    'max' => 5
                ]
            ])
            ->add('comments', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Laissez un avis ici...'
                ]
            ])
            ->getForm();
            
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Modérer le commentaire avant de le sauvegarder
            $originalComment = $feedback->getComments();
            if ($originalComment) {
                $moderatedComment = $this->moderationService->moderateContent($originalComment);
                $feedback->setComments($moderatedComment);
            }
            
            $user = $this->getUser();
            if ($user) {
                $feedback->setUserId($user->getId());
                $feedback->setUser($user);
            } else {
                $feedback->setUserId(null);
            }

            $feedback->setCreatedAt(new \DateTime());
            $em->persist($feedback);
            $em->flush();

            $session = $request->getSession();
            $quizResults = $session->get('quiz_results');
            if ($quizResults) {
                $quizResults['userFeedback'] = $feedback;
                $session->set('quiz_results', $quizResults);
            }

            $this->addFlash('success', 'Votre avis a été enregistré avec succès.');
        }

        return $this->redirectToRoute('app_quiz_final_results');
    }

    #[Route('/quiz/feedback/{id}', name: 'app_quiz_feedback_delete', methods: ['POST'])]
    public function deleteFeedback(Request $request, Feedback $feedback, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || $feedback->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce feedback.');
        }

        if ($this->isCsrfTokenValid('delete'.$feedback->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($feedback);
            $em->flush();
            
            $session = $request->getSession();
            $quizResults = $session->get('quiz_results');
            if ($quizResults && isset($quizResults['userFeedback'])) {
                $quizResults['userFeedback'] = null;
                $session->set('quiz_results', $quizResults);
            }
            
            $this->addFlash('success', 'Votre avis a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_quiz_final_results');
    }

    #[Route('/questionnaire/feedback', name: 'app_questionnaire_feedback')]
    public function feedbackList(EntityManagerInterface $em): Response
    {
        $feedbackRepository = $em->getRepository(Feedback::class);
        $allFeedbacks = $feedbackRepository->findBy([], ['createdAt' => 'DESC']);

        $lastUserFeedbacks = [];
        $processedUsers = [];
        
        foreach ($allFeedbacks as $feedback) {
            $userId = $feedback->getUserId();
            
            if ($feedback->getComments() && 
                $feedback->getComments() !== "Réponse automatique (Quiz)" && 
                trim($feedback->getComments()) !== "" &&
                !in_array($userId, $processedUsers)) {
                
                $lastUserFeedbacks[] = $feedback;
                $processedUsers[] = $userId;
            }
        }

        return $this->render('questionnaire/feedback/index.html.twig', [
            'userFeedbacks' => $lastUserFeedbacks
        ]);
    }

    #[Route('/quiz/certificate', name: 'app_quiz_certificate')]
    public function downloadCertificate(Request $request): Response
    {
        $session = $request->getSession();
        $quizResults = $session->get('quiz_results');
        $eventId = $session->get('quiz_event_id');

        if (!$quizResults || !$eventId) {
            $this->addFlash('error', 'Résultats du quiz non disponibles. Veuillez refaire le quiz.');
            return $this->redirectToRoute('app_quiz_start');
        }

        $score = $quizResults['score'];
        $total = $quizResults['total'];

        $percentage = ($score / $total) * 100;
        if ($percentage < 50) {
            $this->addFlash('error', 'Le certificat n\'est disponible que pour un score de 50% ou plus. Votre score: ' . round($percentage) . '% (' . $score . '/' . $total . ')');
            return $this->redirectToRoute('app_quiz_final_results');
        }

        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour télécharger le certificat.');
            return $this->redirectToRoute('app_login');
        }

        $event = $this->eventRepository->find($eventId);
        if (!$event) {
            $this->addFlash('error', 'Événement non trouvé.');
            return $this->redirectToRoute('app_public_events');
        }

        try {
            $percentage = round($percentage);
            $date = (new \DateTime())->format('d/m/Y');

            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
            <style>
              body        { font-family: DejaVu Sans, sans-serif; color: #1a1a2e; font-size: 14px; padding: 40px; }
              .container  { max-width: 900px; margin: 0 auto; border: 3px solid #667eea; padding: 40px; }
              h1          { font-size: 32px; margin-bottom: 10px; color: #667eea; text-align: center; text-transform: uppercase; }
              .subtitle   { text-align: center; color: #666; font-size: 16px; margin-bottom: 30px; }
              .name       { text-align: center; font-size: 28px; font-weight: bold; color: #764ba2; margin: 20px 0; }
              .event      { text-align: center; font-size: 24px; font-weight: bold; color: #667eea; margin: 20px 0; }
              .score      { text-align: center; font-size: 20px; margin: 20px 0; }
              .badge      { display: inline-block; background: #667eea; color: white; padding: 10px 30px; 
                             font-size: 18px; font-weight: bold; border-radius: 5px; margin-top: 20px; }
              .date       { text-align: center; margin-top: 30px; color: #666; font-size: 16px; }
              .footer     { display: flex; justify-content: space-between; margin-top: 60px; padding: 0 50px; }
              .signature  { text-align: center; }
              .line       { border-top: 2px solid #333; width: 200px; margin: 10px auto; }
            </style></head><body>';

            $html .= '<div class="container">';
            $html .= '<h1>Certificat de Réussite</h1>';
            $html .= '<div class="subtitle">Ce document certifie que</div>';
            $html .= '<div class="name">' . htmlspecialchars($user->getFullName()) . '</div>';
            $html .= '<div style="text-align:center;margin:10px 0;">a réussi le quiz de l\'événement</div>';
            $html .= '<div class="event">' . htmlspecialchars($event->getTitle()) . '</div>';
            $html .= '<div class="score">Score : ' . $score . ' / ' . $total . '</div>';
            $html .= '<div style="text-align:center;"><span class="badge">' . $percentage . '% de réussite</span></div>';
            $html .= '<div class="date">Délivré le ' . $date . '</div>';
            $html .= '<div class="footer">';
            $html .= '<div class="signature"><div>Organisateur</div><div class="line"></div></div>';
            $html .= '<div class="signature"><div>Participant</div><div class="line"></div></div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</body></html>';

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $filename = 'certificat_' . $user->getId() . '_' . $eventId . '_' . date('Ymd_His') . '.pdf';

            return new Response(
                $dompdf->output(),
                200,
                [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du certificat: ' . $e->getMessage());
            return $this->redirectToRoute('app_quiz_final_results');
        }
    }

    #[Route('/quiz/test-pdf', name: 'app_quiz_test_pdf')]
    public function testPdf(): Response
    {
        try {
            $options = new Options();
            $options->set('defaultFont', 'Arial');
            $options->set('isRemoteEnabled', false);
            
            $dompdf = new Dompdf($options);
            
            $html = '<html><body><h1>Test PDF</h1><p>Ceci est un test de génération PDF.</p></body></html>';
            
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            return $dompdf->stream('test.pdf', [
                'Attachment' => true
            ]);
        } catch (\Exception $e) {
            return new Response('Erreur: ' . $e->getMessage());
        }
    }
}