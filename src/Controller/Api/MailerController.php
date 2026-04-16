<?php

namespace App\Controller\Api;

use App\Service\Resource\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/mailer')]
class MailerController extends AbstractController
{
    private MailerService $mailerService;

    public function __construct(MailerService $mailerService)
    {
        $this->mailerService = $mailerService;
    }

    #[Route('/send-confirmation', name: 'api_mailer_send_confirmation', methods: ['POST'])]
    public function sendConfirmationEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], 400);
        }

        $requiredFields = ['email', 'userName', 'resourceType', 'eventName', 'startTime', 'endTime'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return new JsonResponse(['error' => "Le champ '$field' est requis"], 400);
            }
        }

        $success = $this->mailerService->sendReservationConfirmation($data);

        if ($success) {
            return new JsonResponse(['message' => 'Email de confirmation envoyé avec succès'], 200);
        } else {
            return new JsonResponse(['error' => 'Erreur lors de l\'envoi de l\'email'], 500);
        }
    }

    #[Route('/send-notification', name: 'api_mailer_send_notification', methods: ['POST'])]
    public function sendNotificationEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Données JSON invalides'], 400);
        }

        $requiredFields = ['email', 'userName', 'resourceType', 'eventName', 'startTime', 'endTime'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return new JsonResponse(['error' => "Le champ '$field' est requis"], 400);
            }
        }

        $success = $this->mailerService->sendReservationNotification($data);

        if ($success) {
            return new JsonResponse(['message' => 'Email de notification envoyé avec succès'], 200);
        } else {
            return new JsonResponse(['error' => 'Erreur lors de l\'envoi de l\'email'], 500);
        }
    }

    #[Route('/test', name: 'api_mailer_test', methods: ['GET'])]
    public function testMailer(): JsonResponse
    {
        $testData = [
            'email' => 'test@example.com',
            'userName' => 'Utilisateur Test',
            'resourceType' => 'SALLE',
            'salleName' => 'Salle A101',
            'eventName' => 'Réunion Test',
            'startTime' => new \DateTime(),
            'endTime' => new \DateTime('+2 hours'),
            'quantity' => 1
        ];

        $confirmationSuccess = $this->mailerService->sendReservationConfirmation($testData);
        $notificationSuccess = $this->mailerService->sendReservationNotification($testData);

        return new JsonResponse([
            'confirmation_sent' => $confirmationSuccess,
            'notification_sent' => $notificationSuccess,
            'message' => 'Test d\'envoi d\'emails effectué'
        ]);
    }
}
