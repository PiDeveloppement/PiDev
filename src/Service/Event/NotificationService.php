<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Notification;
use App\Entity\User\UserModel;
use App\Repository\Event\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des notifications pour les participants.
 * Centralise la creation des notifications selon leur type metier.
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository
    ) {}

    /**
     * Notification de confirmation d'inscription a un evenement.
     * Declenchee lors de la creation d'un ticket.
     */
    public function createConfirmation(UserModel $user, Event $event): Notification
    {
        return $this->create(
            $user,
            $event,
            Notification::TYPE_CONFIRMATION,
            'Inscription confirmee',
            sprintf(
                "Votre inscription a l'evenement '%s' est confirmee. Telechargez votre billet dans Mes billets.",
                $event->getTitle()
            ),
            '🎫'
        );
    }

    /**
     * Notification de rappel (24h avant l'evenement).
     * Declenchee par une commande cron/manuelle.
     */
    public function createReminder(UserModel $user, Event $event): Notification
    {
        return $this->create(
            $user,
            $event,
            Notification::TYPE_REMINDER,
            'Evenement demain',
            sprintf(
                "Votre evenement '%s' commence demain. Preparez votre billet !",
                $event->getTitle()
            ),
            '⏰'
        );
    }

    /**
     * Notification de modification d'un evenement.
     * Declenchee lorsque l'organisateur modifie date/lieu/prix d'un event reserve.
     */
    public function createModification(UserModel $user, Event $event): Notification
    {
        return $this->create(
            $user,
            $event,
            Notification::TYPE_MODIFICATION,
            'Evenement modifie',
            sprintf(
                "Les details de l'evenement '%s' ont ete mis a jour. Consultez les nouvelles informations.",
                $event->getTitle()
            ),
            '⚠️'
        );
    }

    /**
     * Notification d'annulation d'un evenement.
     * Declenchee lorsque l'organisateur supprime un event reserve.
     */
    public function createCancellation(UserModel $user, Event $event): Notification
    {
        return $this->create(
            $user,
            $event,
            Notification::TYPE_CANCELLATION,
            'Evenement annule',
            sprintf(
                "L'evenement '%s' a ete annule. Nous nous excusons pour la gene occasionnee.",
                $event->getTitle()
            ),
            '❌'
        );
    }

    /**
     * Notification de bienvenue apres validation du billet (scan QR).
     * Declenchee lorsque le ticket est scanne et passe a USED.
     */
    public function createWelcome(UserModel $user, Event $event): Notification
    {
        return $this->create(
            $user,
            $event,
            Notification::TYPE_WELCOME,
            'Bienvenue !',
            sprintf(
                "Bienvenue a '%s' ! Nous vous souhaitons une excellente participation.",
                $event->getTitle()
            ),
            '🎉'
        );
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues.
     * Delegue au repository qui utilise DQL UPDATE en masse.
     */
    public function markAllAsRead(UserModel $user): int
    {
        return $this->notificationRepository->markAllAsRead($user);
    }

    /**
     * Recupere le nombre de notifications non lues.
     */
    public function getUnreadCount(UserModel $user): int
    {
        return $this->notificationRepository->countUnread($user);
    }

    /**
     * Recupere les dernieres notifications d'un utilisateur.
     *
     * @return Notification[]
     */
    public function getLatest(UserModel $user, int $limit = 5): array
    {
        return $this->notificationRepository->findLatestForUser($user, $limit);
    }

    /**
     * Methode interne de creation d'une notification.
     * Persiste et flush immediatement pour que la notification
     * soit disponible des le rechargement de la page.
     */
    private function create(
        UserModel $user,
        ?Event $event,
        string $type,
        string $title,
        string $message,
        ?string $icon = null
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setEvent($event);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setIcon($icon);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}