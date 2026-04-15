<?php

namespace App\Repository\Resource;

use App\Entity\Resource\ReservationResource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationResource::class);
    }

    // Traduction de isSalleOccupee
    public function isSalleOccupee(int $salleId, \DateTimeInterface $start, \DateTimeInterface $end, int $excludeId = 0): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.salle = :salleId')
            ->andWhere('r.id != :excludeId')
            ->andWhere('r.startTime < :end AND r.endTime > :start')
            ->setParameter('salleId', $salleId)
            ->setParameter('excludeId', $excludeId)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    // Traduction de getStockOccupe
    public function getStockOccupe(int $eqId, \DateTimeInterface $start, \DateTimeInterface $end, int $excludeId = 0): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('SUM(r.quantity)')
            ->where('r.equipement = :eqId')
            ->andWhere('r.id != :excludeId')
            ->andWhere('r.startTime < :end AND r.endTime > :start')
            ->setParameter('eqId', $eqId)
            ->setParameter('excludeId', $excludeId)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findByFilters(array $filters = [], string $sortBy = 'startTime', string $direction = 'desc')
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.salle', 's')
            ->leftJoin('r.equipement', 'e');

        if (!empty($filters['name'])) {
            $qb->andWhere('s.name LIKE :name OR e.name LIKE :name')
               ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['resourceType'])) {
            $qb->andWhere('r.resourceType = :resourceType')
               ->setParameter('resourceType', $filters['resourceType']);
        }

        $qb->orderBy('r.' . $sortBy, $direction);

        return $qb->getQuery()->getResult();
    }
}