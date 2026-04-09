<?php

namespace App\Repository\Event;

use App\Entity\Event\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', $status)
            ->orderBy('e.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function createBackOfficeListQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb->andWhere('LOWER(e.title) LIKE :search')
                ->setParameter('search', mb_strtolower($search) . '%');
        }

        $status = (string) ($filters['status'] ?? '');
        $now = new \DateTimeImmutable();
        if ($status === 'avenir') {
            $qb->andWhere('e.startDate > :now')->setParameter('now', $now);
        } elseif ($status === 'encours') {
            $qb->andWhere('e.startDate <= :now AND e.endDate >= :now')->setParameter('now', $now);
        } elseif ($status === 'termine') {
            $qb->andWhere('e.endDate < :now')->setParameter('now', $now);
        }

        $categoryId = (int) ($filters['category'] ?? 0);
        if ($categoryId > 0) {
            $qb->andWhere('e.categoryId = :categoryId')->setParameter('categoryId', $categoryId);
        }

        $price = (string) ($filters['price'] ?? '');
        if ($price === 'free') {
            $qb->andWhere('e.isFree = true');
        } elseif ($price === 'paid') {
            $qb->andWhere('e.isFree = false');
        }

        return $qb->orderBy('e.startDate', 'DESC');
    }

    public function countByCategoryId(int $categoryId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.categoryId = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}