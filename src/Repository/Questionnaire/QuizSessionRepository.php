<?php

namespace App\Repository\Questionnaire;

use App\Entity\Questionnaire\QuizSession;
use App\Entity\User\UserModel;
use App\Entity\Event\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizSession>
 */
class QuizSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizSession::class);
    }

    /**
     * Trouve une session par token
     */
    public function findBySessionToken(string $token): ?QuizSession
    {
        return $this->createQueryBuilder('qs')
            ->where('qs.sessionToken = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les sessions actives d'un utilisateur
     */
    public function findActiveSessionsByUser(?UserModel $user): array
    {
        return $this->createQueryBuilder('qs')
            ->where('qs.user = :user')
            ->andWhere('qs.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', ['pending', 'started'])
            ->orderBy('qs.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les sessions expirées
     */
    public function findExpiredSessions(): array
    {
        $expirationDate = new \DateTime('-2 hours');
        
        return $this->createQueryBuilder('qs')
            ->where('qs.startedAt < :expiration')
            ->andWhere('qs.status = :status')
            ->setParameter('expiration', $expirationDate)
            ->setParameter('status', 'started')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les sessions par IP pour détecter les abus
     */
    public function countSessionsByIp(string $ipAddress, \DateTime $since = null): int
    {
        $qb = $this->createQueryBuilder('qs')
            ->select('COUNT(qs.id)')
            ->where('qs.ipAddress = :ip')
            ->setParameter('ip', $ipAddress);

        if ($since) {
            $qb->andWhere('qs.startedAt >= :since')
               ->setParameter('since', $since);
        }

        return (int) $qb->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si un utilisateur a déjà une session active pour un événement
     */
    public function hasActiveSessionForEvent(?UserModel $user, ?Event $event): bool
    {
        if (!$user || !$event) {
            return false;
        }

        $count = $this->createQueryBuilder('qs')
            ->select('COUNT(qs.id)')
            ->where('qs.user = :user')
            ->andWhere('qs.event = :event')
            ->andWhere('qs.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('event', $event)
            ->setParameter('statuses', ['pending', 'started'])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Nettoie les anciennes sessions expirées
     */
    public function cleanupExpiredSessions(): int
    {
        $expiredSessions = $this->findExpiredSessions();
        $count = count($expiredSessions);

        foreach ($expiredSessions as $session) {
            $session->setStatus('aborted');
            $this->getEntityManager()->persist($session);
        }

        $this->getEntityManager()->flush();

        return $count;
    }

    /**
     * Statistiques des sessions
     */
    public function getSessionStats(\DateTime $from = null, \DateTime $to = null): array
    {
        $qb = $this->createQueryBuilder('qs')
            ->select('qs.status, COUNT(qs.id) as count')
            ->groupBy('qs.status');

        if ($from) {
            $qb->andWhere('qs.startedAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('qs.startedAt <= :to')
               ->setParameter('to', $to);
        }

        $results = $qb->getQuery()->getResult();
        
        $stats = [
            'pending' => 0,
            'started' => 0,
            'completed' => 0,
            'aborted' => 0,
        ];

        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['count'];
        }

        return $stats;
    }
}