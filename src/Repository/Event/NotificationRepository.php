<?php

namespace App\Repository\Event;

use App\Entity\Event\Notification;
use App\Entity\User\UserModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Compte les notifications non lues d'un utilisateur.
     * Utilise Query Builder avec COUNT pour performance optimale
     * (ne charge pas les lignes, juste le nombre).
     */
    public function countUnread(UserModel $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recupere les N dernieres notifications d'un utilisateur.
     * Utilise Query Builder avec ORDER BY DESC et LIMIT.
     *
     * @return Notification[]
     */
    public function findLatestForUser(UserModel $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtre les notifications par type pour un utilisateur.
     * Utilise Query Builder avec WHERE multi-conditions.
     *
     * @return Notification[]
     */
    public function findByType(UserModel $user, string $type): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues.
     * Utilise DQL UPDATE en masse : une seule requete SQL pour N lignes,
     * au lieu d'une boucle PHP (performance).
     * Retourne le nombre de lignes affectees.
     */
    public function markAllAsRead(UserModel $user): int
    {
        return $this->getEntityManager()
            ->createQuery(
                'UPDATE App\Entity\Event\Notification n
                 SET n.isRead = :read
                 WHERE n.user = :user
                 AND n.isRead = :unread'
            )
            ->setParameter('read', true)
            ->setParameter('unread', false)
            ->setParameter('user', $user)
            ->execute();
    }

    /**
     * Supprime les notifications plus anciennes que X jours.
     * Utilise DQL DELETE pour le nettoyage automatique de la base.
     * Retourne le nombre de lignes supprimees.
     */
    public function deleteOldNotifications(int $daysOld = 60): int
    {
        $limitDate = new \DateTimeImmutable('-' . $daysOld . ' days');

        return $this->getEntityManager()
            ->createQuery(
                'DELETE FROM App\Entity\Event\Notification n
                 WHERE n.createdAt < :limit'
            )
            ->setParameter('limit', $limitDate)
            ->execute();
    }
}