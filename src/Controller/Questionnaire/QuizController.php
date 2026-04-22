<?php

namespace App\Controller\Questionnaire;

use App\Entity\Questionnaire\Question;
use App\Entity\Questionnaire\Feedback;
use App\Entity\Questionnaire\QuizAnswer;
use App\Entity\Event\Event;
use App\Entity\Event\Ticket;
use App\Form\Questionnaire\FeedbackType;
use App\Form\Questionnaire\QuizAnswerType;
use App\Repository\Questionnaire\QuestionRepository;
use App\Repository\Event\EventRepository;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    public function __construct(EventRepository $eventRepository, UserRepository $userRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    #[Route('/quiz/start', name: 'app_quiz_start')]
    public function start(Request $request, QuestionRepository $repo, UserRepository $user, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $eventId = $request->query->get('event_id');
        
        // Vérifier si l'utilisateur est connecté et a le bon rôle (roleId 1 ou 5)
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

        if (!$eventId) {
            error_log('=== QUIZ DEBUG === No event ID provided');
            $this->addFlash('error', 'Veuillez sélectionner un événement pour passer le quiz.');

            return $this->redirectToRoute('app_public_events');
        }

        // Filtrer les questions par événement si un event_id est fourni
        if ($eventId) {
            error_log('=== QUIZ DEBUG === Event ID received: ' . $eventId);
            
            // D'abord, vérifier si l'événement existe
            $event = $this->eventRepository->find($eventId);
            if (!$event) {
                error_log('=== QUIZ DEBUG === Event not found with ID: ' . $eventId);
                $this->addFlash('error', 'Événement non trouvé.');
                return $this->redirectToRoute('app_public_events');
            }
            error_log('=== QUIZ DEBUG === Event found: ' . $event->getTitle());

            $usedTicketCount = (int) $em->getRepository(Ticket::class)->count([
                'event' => $event,
                'user' => $user,
                'isUsed' => true,
            ]);

            if ($usedTicketCount <= 0) {
                $this->addFlash('error', 'Le quiz est disponible uniquement après participation effective à l\'événement (billet USED).');

                return $this->redirectToRoute('app_public_event_show', ['id' => (int) $eventId]);
            }
            
            $questions = $repo->createQueryBuilder('q')
                ->where('q.event = :eventId')
                ->setParameter('eventId', $eventId)
                ->getQuery()
                ->getResult();
                
            error_log('=== QUIZ DEBUG === Questions found for event: ' . count($questions));
            
            // Afficher les IDs des questions trouvées
            foreach ($questions as $q) {
                error_log('=== QUIZ DEBUG === Question ID: ' . $q->getId() . ' - Event ID: ' . ($q->getEvent() ? $q->getEvent()->getId() : 'NULL'));
            }
            
            // Message flash pour montrer à l'utilisateur ce qui se passe
            $this->addFlash('info', 'Événement: ' . $event->getTitle() . ' (ID: ' . $eventId . ') - Questions trouvées: ' . count($questions));
                
            // Si aucune question n'est trouvée pour cet événement, afficher un message d'erreur
            if (empty($questions)) {
                // Vérifier toutes les questions et leurs événements
                $allQuestions = $repo->findAll();
                error_log('=== QUIZ DEBUG === Total questions in DB: ' . count($allQuestions));
                
                foreach ($allQuestions as $q) {
                    $eventTitle = $q->getEvent() ? $q->getEvent()->getTitle() : 'NULL';
                    $eventId2 = $q->getEvent() ? $q->getEvent()->getId() : 'NULL';
                    error_log('=== QUIZ DEBUG === Question ' . $q->getId() . ' -> Event: ' . $eventTitle . ' (ID: ' . $eventId2 . ')');
                }
                
                $this->addFlash('error', 'Aucune question disponible pour cet événement. Veuillez contacter l\'administrateur.');
                return $this->redirectToRoute('app_public_events');
            }
        }
        
        shuffle($questions);
        $questions = array_slice($questions, 0, 10);

        // Stocker les questions en session
        $session = $this->container->get('request_stack')->getSession();
        $session->set('quiz_questions', $questions);
        $session->set('quiz_answers', []);
        $session->set('quiz_event_id', $eventId);

        return $this->render('questionnaire/quiz/index.html.twig', [
            'questions' => $questions,
            'eventId' => $eventId
        ]);
    }

    #[Route('/quiz/validate-answer', name: 'app_quiz_validate_answer', methods: ['POST'])]
    public function validateAnswer(Request $request, ValidatorInterface $validator): Response
    {
        $session = $request->getSession();
        $questionId = $request->request->get('questionId');
        $answer = $request->request->get('answer');

        // Créer une réponse de quiz pour la validation
        $quizAnswer = new QuizAnswer();
        $quizAnswer->setQuestionId($questionId);
        $quizAnswer->setAnswer($answer);

        // Valider la réponse
        $errors = $validator->validate($quizAnswer);

        if (count($errors) > 0) {
            // Retourner les erreurs en format JSON
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            
            return $this->json([
                'success' => false,
                'errors' => $errorMessages
            ]);
        }

        // Si la validation passe, stocker la réponse en session
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
        // Debug: Vérifier la session avant tout
        $session = $request->getSession();
        $sessionId = $session->getId();
        $userId = $session->get('user_id');
        $userEmail = $session->get('user_email');
        
        // Log pour debug
        error_log('=== QUIZ CONTROLLER DEBUG ===');
        error_log('Session ID: ' . $sessionId);
        error_log('User ID in session: ' . ($userId ?? 'NULL'));
        error_log('User Email in session: ' . ($userEmail ?? 'NULL'));
        error_log('Session data: ' . print_r($session->all(), true));
        error_log('=============================');
        
        // Récupérer les réponses validées depuis la session
        $data = $session->get('quiz_answers', []); 
        $evaluation = $request->request->all('evaluation'); 
        
        $results = [];
        $score = 0;

        foreach ($data as $questionId => $userAnswer) {
            $question = $repo->find($questionId);
            if ($question) {
                $isCorrect = (trim(strtolower($question->getReponse())) === trim(strtolower($userAnswer)));
                if ($isCorrect) $score++;

                $fb = new Feedback();
                $fb->setQuestion($question);
                $fb->setReponseDonnee($userAnswer);

                // Associer l'utilisateur connecté au feedback
                $user = $this->getUser();
                if ($user) {
                    $fb->setUserId($user->getId());
                    $fb->setUser($user);
                } else {
                    // Si aucun utilisateur connecté, utiliser une valeur par défaut ou gérer l'erreur
                    $fb->setUserId(null);
                }

                // Pour les réponses du quiz, on met un commentaire par défaut
                $fb->setComments("Réponse automatique (Quiz)");
                $fb->setEtoiles(0);

                $em->persist($fb);
                $results[] = [
                    'question' => $question->getTexte(),
                    'userAnswer' => $userAnswer,
                    'correctAnswer' => $question->getReponse(),
                    'isCorrect' => $isCorrect
                ];
            }
        }
        
        $em->flush();
        
        // Traiter l'évaluation si elle existe
        $userFeedback = null;
        if (isset($evaluation['comments']) && isset($evaluation['etoiles'])) {
            $userFeedback = new Feedback();
            $userFeedback->setComments($evaluation['comments']);
            $userFeedback->setEtoiles((int)$evaluation['etoiles']);
            
            // Associer l'utilisateur connecté
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
        
        // Stocker les résultats en session pour la page dédiée
        $session->set('quiz_results', [
            'results' => $results,
            'score' => $score,
            'total' => count($results) ?: 1,
            'userFeedback' => $userFeedback
        ]);

        // Rediriger vers la page de résultats finale
        return $this->redirectToRoute('app_quiz_final_results');
    }

    #[Route('/quiz/results', name: 'app_quiz_results')]
    public function results(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $quizResults = $session->get('quiz_results');

        // Récupérer les feedbacks existants pour les afficher
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

    #[Route('/quiz/feedback/save', name: 'app_quiz_feedback_save', methods: ['POST'])]
    public function saveFeedback(Request $request, EntityManagerInterface $em): Response
    {
        $feedback = new Feedback();
        
        // Créer le même formulaire que dans le template
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
            // Associer l'utilisateur connecté
            $user = $this->getUser();
            if ($user) {
                $feedback->setUserId($user->getId());
                $feedback->setUser($user);
            } else {
                $feedback->setUserId(null);
            }

            $em->persist($feedback);
            $em->flush();

            $this->addFlash('success', 'Merci pour votre avis !');
        }

        // REDIRECTION vers la page des résultats finaux du quiz
        return $this->redirectToRoute('app_quiz_final_results');
    }

    #[Route('/quiz/feedback/{id}/edit', name: 'app_quiz_feedback_edit', methods: ['GET', 'POST'])]
    public function editFeedback(Request $request, Feedback $feedback, EntityManagerInterface $em): Response
    {
        // Vérifier que le feedback appartient à l'utilisateur connecté
        $user = $this->getUser();
        if (!$user || $feedback->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce feedback.');
        }

        // Traiter directement les données du formulaire sans créer de formulaire Symfony
        if ($request->isMethod('POST')) {
            $etoiles = $request->request->get('etoiles');
            $comments = $request->request->get('comments');

            // Valider et mettre à jour les données
            if ($etoiles !== null && $comments !== null) {
                $feedback->setEtoiles((int)$etoiles);
                $feedback->setComments($comments);
                
                $em->flush();
                
                // Mettre à jour les données en session pour l'affichage
                $session = $request->getSession();
                $quizResults = $session->get('quiz_results');
                if ($quizResults && isset($quizResults['userFeedback'])) {
                    $quizResults['userFeedback'] = $feedback;
                    $session->set('quiz_results', $quizResults);
                }
                
                $this->addFlash('success', 'Votre avis a été modifié avec succès.');
            }
        }

        // Toujours rediriger vers la page des résultats finaux
        return $this->redirectToRoute('app_quiz_final_results');
    }

    #[Route('/quiz/feedback/{id}', name: 'app_quiz_feedback_delete', methods: ['POST'])]
    public function deleteFeedback(Request $request, Feedback $feedback, EntityManagerInterface $em): Response
    {
        // Vérifier que le feedback appartient à l'utilisateur connecté
        $user = $this->getUser();
        if (!$user || $feedback->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce feedback.');
        }

        if ($this->isCsrfTokenValid('delete'.$feedback->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($feedback);
            $em->flush();
            
            // Mettre à jour les données en session pour l'affichage
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
        // Récupérer tous les feedbacks triés par date décroissante
        $feedbackRepository = $em->getRepository(Feedback::class);
        $allFeedbacks = $feedbackRepository->findBy([], ['createdAt' => 'DESC']);

        // Regrouper par utilisateur et garder uniquement le dernier vrai commentaire
        $lastUserFeedbacks = [];
        $processedUsers = [];
        
        foreach ($allFeedbacks as $feedback) {
            $userId = $feedback->getUserId();
            
            // Vérifier si c'est un vrai commentaire (pas réponse automatique)
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

        // Vérifier que le score est entre 5 et 10
        if ($score < 5 || $score > 10) {
            $this->addFlash('error', 'Le certificat n\'est disponible que pour un score entre 5 et 10. Votre score: ' . $score . '/' . $total);
            return $this->redirectToRoute('app_quiz_final_results');
        }

        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour télécharger le certificat.');
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'événement
        $event = $this->eventRepository->find($eventId);
        if (!$event) {
            $this->addFlash('error', 'Événement non trouvé.');
            return $this->redirectToRoute('app_public_events');
        }

        try {
            $percentage = round(($score / $total) * 100);
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
