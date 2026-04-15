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
            ->leftJoin('t.event', 'e')
            ->select('COUNT(DISTINCT e.id)')
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
            $tokens = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim($search)) ?: '') ?: []));

            foreach ($tokens as $index => $token) {
                $startParam = 'search_start_' . $index;
                $wordParam = 'search_word_' . $index;

                $orX = $qb->expr()->orX(
                    "LOWER(COALESCE(t.ticketCode, '')) LIKE :{$startParam}",
                    "LOWER(COALESCE(t.ticketCode, '')) LIKE :{$wordParam}",
                    "LOWER(COALESCE(e.title, '')) LIKE :{$startParam}",
                    "LOWER(COALESCE(e.title, '')) LIKE :{$wordParam}",
                    "LOWER(COALESCE(u.firstName, '')) LIKE :{$startParam}",
                    "LOWER(COALESCE(u.firstName, '')) LIKE :{$wordParam}",
                    "LOWER(COALESCE(u.lastName, '')) LIKE :{$startParam}",
                    "LOWER(COALESCE(u.lastName, '')) LIKE :{$wordParam}"
                );

                $qb->andWhere($orX)
                    ->setParameter($startParam, $token . '%')
                    ->setParameter($wordParam, '% ' . $token . '%');
            }
        }

        if ($eventFilter !== null && $eventFilter !== '') {
            $qb->andWhere('e.id = :eventId')
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
