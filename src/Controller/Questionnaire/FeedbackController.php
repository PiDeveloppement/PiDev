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
        $feedbacks = $entityManager // On met au pluriel ici
            ->getRepository(Feedback::class)
            ->findAll();

        return $this->render('questionnaire/feedback/index.html.twig', [
            'feedbacks' => $feedbacks, // On envoie 'feedbacks' au pluriel pour correspondre au Twig
        ]);
    }

 
    #[Route('/{id}', name: 'app_questionnaire_feedback_show', methods: ['GET'])]
    public function show(Feedback $feedback): Response
    {
        return $this->render('questionnaire/feedback/show.html.twig', [
            'feedback' => $feedback,
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
