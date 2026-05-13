<?php

namespace App\Repository\Budget;

use App\Entity\Budget\Budget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Budget>
 */
class BudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Budget::class);
    }

    public function existsForEvent(int $eventId, ?int $excludeId = null): bool
    {
        // Verifie qu'un evenement ne porte qu'un seul budget actif dans l'application.
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.eventId = :eventId')
            ->setParameter('eventId', $eventId);

        if ($excludeId !== null && $excludeId > 0) {
            $qb->andWhere('b.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}




