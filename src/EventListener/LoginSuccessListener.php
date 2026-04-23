<?php

namespace App\EventListener;

use App\Bundle\NotificationBundle\Service\NotificationService;
use App\Entity\User\UserModel;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessListener
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if ($user instanceof UserModel && $user->getPhone()) {
            try {
                error_log('=== LoginSuccessListener - Envoi SMS ===');
                error_log('Numéro de téléphone: ' . $user->getPhone());
                $this->notificationService->sendSms(
                    $user->getPhone(),
                    'EventFlow: Nouvelle connexion détectée sur votre compte.'
                );
                error_log('SMS envoyé avec succès via LoginSuccessListener');
            } catch (\Exception $e) {
                error_log('Erreur envoi SMS LoginSuccessListener: ' . $e->getMessage());
            }
        }
    }
}
