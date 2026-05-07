<?php

namespace App\Controller\Questionnaire;

use App\Entity\Event\Event;
use App\Entity\Questionnaire\Question;
use App\Service\Questionnaire\QuestionGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

#[Route('/api')]
class QuestionGenerationController extends AbstractController
{
    private QuestionGenerator $questionGenerator;
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(
        QuestionGenerator $questionGenerator,
        EntityManagerInterface $entityManager,
        Security $security
    ) {
        $this->questionGenerator = $questionGenerator;
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    #[Route('/generate-question/{eventId}', name: 'api_generate_question', methods: ['POST'])]
    public function generateQuestion(int $eventId, Request $request): JsonResponse
    {
        try {
            // Vérifier l'authentification
            $user = $this->security->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Utilisateur non authentifié'], 401);
            }

            // Récupérer l'événement
            $event = $this->entityManager->getRepository(Event::class)->find($eventId);
            if (!$event) {
                return new JsonResponse(['error' => 'Événement non trouvé'], 404);
            }

            // Vérifier les permissions (l'utilisateur doit être le créateur ou admin)
            if ($event->getCreator()?->getId() !== ($user instanceof \App\Entity\User\UserModel ? $user->getId() : null) && !$this->isGranted('ROLE_ADMIN')) {
                return new JsonResponse(['error' => 'Permission refusée'], 403);
            }

            // Générer la question avec l'IA
            $questionData = $this->questionGenerator->generateFromEvent($event);

            // Créer l'entité Question
            $question = new Question();
            $question->setTexte($questionData['texte']);
            $question->setReponse($questionData['reponse']);
            $question->setPoints((int) $questionData['points']);
            $question->setOption1($questionData['option1']);
            $question->setOption2($questionData['option2']);
            $question->setOption3($questionData['option3']);
            $question->setEvent($event);
            $question->setUser($user instanceof \App\Entity\User\UserModel ? $user : null);

            // Sauvegarder la question
            $this->entityManager->persist($question);
            $this->entityManager->flush();

            // Retourner la question créée
            return new JsonResponse([
                'success' => true,
                'question' => [
                    'id' => $question->getId(),
                    'texte' => $question->getTexte(),
                    'reponse' => $question->getReponse(),
                    'points' => $question->getPoints(),
                    'option1' => $question->getOption1(),
                    'option2' => $question->getOption2(),
                    'option3' => $question->getOption3(),
                    'eventId' => $question->getEvent()?->getId(),
                    'eventTitle' => $question->getEvent()?->getTitle()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la génération de la question: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/preview-question/{eventId}', name: 'api_preview_question', methods: ['POST'])]
    public function previewQuestion(int $eventId, Request $request): JsonResponse
    {
        try {
            // Vérifier l'authentification
            $user = $this->security->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Utilisateur non authentifié'], 401);
            }

            // Récupérer l'événement
            $event = $this->entityManager->getRepository(Event::class)->find($eventId);
            if (!$event) {
                return new JsonResponse(['error' => 'Événement non trouvé'], 404);
            }

            // Vérifier les permissions
            if ($event->getCreator()?->getId() !== ($user instanceof \App\Entity\User\UserModel ? $user->getId() : null) && !$this->isGranted('ROLE_ADMIN')) {
                return new JsonResponse(['error' => 'Permission refusée'], 403);
            }

            // Générer la question avec l'IA (sans la sauvegarder)
            $questionData = $this->questionGenerator->generateFromEvent($event);

            // Retourner l'aperçu de la question
            return new JsonResponse([
                'success' => true,
                'preview' => [
                    'texte' => $questionData['texte'],
                    'reponse' => $questionData['reponse'],
                    'points' => $questionData['points'],
                    'option1' => $questionData['option1'],
                    'option2' => $questionData['option2'],
                    'option3' => $questionData['option3'],
                    'eventTitle' => $event->getTitle()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la génération de l\'aperçu: ' . $e->getMessage()
            ], 500);
        }
    }
}