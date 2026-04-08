<?php

namespace App\Controller\Questionnaire;

use App\Entity\Questionnaire\Feedback;
use App\Form\Questionnaire\FeedbackType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/questionnaire/feedback')]
final class FeedbackController extends AbstractController
{
    #[Route(name: 'app_questionnaire_feedback_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // 1. Récupérer tous les feedbacks du plus récent au plus ancien
        $allFeedbacks = $entityManager
            ->getRepository(Feedback::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $usersLastFeedback = [];

        foreach ($allFeedbacks as $feedback) {
            $userId = $feedback->getUserId();

            // Si on n'a pas encore ce user ET que c'est un vrai avis (pas quiz)
            if (!isset($usersLastFeedback[$userId])) {
                if ($feedback->getComments() !== "Réponse automatique (Quiz)" && 
                    $feedback->getEtoiles() > 0) {
                    
                    $usersLastFeedback[$userId] = $feedback;
                }
            }
        }

        return $this->render('questionnaire/feedback/index.html.twig', [
            // On envoie le tableau filtré
            'feedbacks' => array_values($usersLastFeedback), 
        ]);
    }

 
    #[Route('/{id}', name: 'app_questionnaire_feedback_show', methods: ['GET'])]
    public function show(Feedback $feedback): Response
    {
        return $this->render('questionnaire/feedback/show.html.twig', [
            'feedback' => $feedback,
        ]);
    }

   #[Route('/show-all', name: 'app_questionnaire_feedback_show_all', methods: ['GET'])]
public function showAllFeedbacks(EntityManagerInterface $entityManager): Response
{
    $feedbackRepository = $entityManager->getRepository(Feedback::class);
    
    // 1. On récupère tout, trié par date décroissante pour avoir les plus récents en premier
    $allFeedbacks = $feedbackRepository->findBy([], ['createdAt' => 'DESC']);

    $usersLastFeedback = [];
    
    foreach ($allFeedbacks as $feedback) {
        $userId = $feedback->getUserId();
        
        // Si on a déjà traité cet utilisateur, on passe au suivant (évite la répétition)
        if (isset($usersLastFeedback[$userId])) {
            continue;
        }

        // 2. On vérifie si c'est le feedback "final" (celui qui contient le commentaire et la note)
        // On exclut les réponses automatiques et on vérifie qu'il y a une note (etoiles > 0)
        if ($feedback->getComments() !== "Réponse automatique (Quiz)" && 
            $feedback->getEtoiles() > 0) {
            
            $usersLastFeedback[$userId] = $feedback;
        }
    }
    
    return $this->render('questionnaire/feedback/show_all.html.twig', [
        'userFeedbacks' => array_values($usersLastFeedback)
    ]);
}

    #[Route('/{id}/edit', name: 'app_questionnaire_feedback_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Feedback $feedback, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_questionnaire_feedback_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('questionnaire/feedback/edit.html.twig', [
            'feedback' => $feedback,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_questionnaire_feedback_delete', methods: ['POST'])]
    public function delete(Request $request, Feedback $feedback, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$feedback->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($feedback);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_questionnaire_feedback_index', [], Response::HTTP_SEE_OTHER);
    }
}
