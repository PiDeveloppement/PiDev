<?php

namespace App\Controller\Auth;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LandingPageController extends AbstractController
{
   // private FeedbackService $feedbackService;

   // public function __construct(FeedbackService $feedbackService)
  //  {
  //      $this->feedbackService = $feedbackService;
  //  }

    #[Route('/', name: 'app_landing')]
    public function index(): Response
    {
     
        return $this->render('auth/landing.html.twig');
    }

    

    #[Route('/signup', name: 'app_signup_redirect')]
    public function signupRedirect(): Response
    {
        return $this->redirectToRoute('app_register');
    }

    #[Route('/events/public', name: 'app_public_events')]
    public function publicEvents(): Response
    {
        return $this->redirectToRoute('app_events_public');
    }

    #[Route('/feedback', name: 'app_feedback_view')]
    public function feedbackView(): Response
    {
        try {
           // $stats = $this->feedbackService->getStatistiquesDetaillees();
          //  $feedbacks = $this->feedbackService->getFeedbacksAvecDetails();

            return $this->render('auth/feedback_view.html.twig', [
            //    'stats' => $stats,
             //   'feedbacks' => $feedbacks
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de charger les feedbacks: ' . $e->getMessage());
            return $this->redirectToRoute('app_landing');
        }
    }

   #[Route('/demo-video', name: 'app_demo_video')]
public function demoVideo(): Response
{
    // Vérifions que le fichier existe
    $templatePath = $this->getParameter('kernel.project_dir') . '/templates/auth/demo_video.html.twig';
    if (!file_exists($templatePath)) {
        throw new \Exception("Template non trouvé: " . $templatePath);
    }
    
    return $this->render('auth/demo_video.html.twig');
}

    #[Route('/certificate', name: 'app_certificate')]
    public function certificate(): Response
    {
        return $this->redirectToRoute('app_participant_quiz');
    }
}