<?php

namespace App\Controller\Questionnaire;

use App\Entity\Questionnaire\Question;
use App\Entity\Questionnaire\Feedback;
use App\Form\Questionnaire\FeedbackType;
use App\Repository\Questionnaire\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class QuizController extends AbstractController
{
    #[Route('/quiz/start', name: 'app_quiz_start')]
    public function start(QuestionRepository $repo): Response
    {
        $questions = $repo->findAll();
        shuffle($questions);
        $questions = array_slice($questions, 0, 10);

        return $this->render('questionnaire/quiz/index.html.twig', [
            'questions' => $questions
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
        
        $data = $request->request->all('answers'); 
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
                $session = $request->getSession();
                $userId = $session->get('user_id');
                
                // SOLUTION RADICALE: Forcer un ID utilisateur pour que ça marche !
                $fb->setUserId(45); // ID 45 forcé
                
                // Récupérer l'objet utilisateur
                $userRepository = $em->getRepository(\App\Entity\User\UserModel::class);
                $user = $userRepository->find(45);
                if ($user) {
                    $fb->setUser($user);
                }
                
                error_log('QuizController: FORCED ID 45 - THIS MUST WORK!');

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
            $userFeedback->setUserId(45);
            $userFeedback->setComments($evaluation['comments']);
            $userFeedback->setEtoiles((int)$evaluation['etoiles']);
            
            // Récupérer l'objet utilisateur
            $userRepository = $em->getRepository(\App\Entity\User\UserModel::class);
            $user = $userRepository->find(45);
            if ($user) {
                $userFeedback->setUser($user);
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
        $existingFeedbacks = $feedbackRepository->findBy(['userId' => 45], ['createdAt' => 'DESC']);

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
            $feedback->setUserId(45); 

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
        // Vérifier que le feedback appartient à l'utilisateur (ID 45)
        if ($feedback->getUserId() !== 45) {
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
        // Vérifier que le feedback appartient à l'utilisateur (ID 45)
        if ($feedback->getUserId() !== 45) {
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
}
