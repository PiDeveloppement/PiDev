<?php

namespace App\Repository\Event;

use App\Entity\Event\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUniqueEvents(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(DISTINCT t.eventId)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function createFilteredQueryBuilder(string $search, $eventFilter, $statusFilter): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.event', 'e')
            ->leftJoin('t.user', 'u')
            ->addSelect('e', 'u')
            ->orderBy('t.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('t.ticketCode LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($eventFilter !== null && $eventFilter !== '') {
            $qb->andWhere('t.eventId = :eventId')
                ->setParameter('eventId', (int) $eventFilter);
        }

        if ($statusFilter === 'used') {
            $qb->andWhere('t.isUsed = :isUsed')->setParameter('isUsed', true);
        } elseif ($statusFilter === 'unused') {
            $qb->andWhere('t.isUsed = :isUsed')->setParameter('isUsed', false);
        }

        return $qb;
    }
}
