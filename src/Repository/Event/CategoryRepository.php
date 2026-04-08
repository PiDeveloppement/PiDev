<?php

namespace App\Repository\Event;

use App\Entity\Event\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function createBackOfficeListQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb->andWhere('LOWER(c.name) LIKE :search')
                ->setParameter('search', mb_strtolower($search) . '%');
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'actif') {
            $qb->andWhere('c.isActive = true');
        } elseif ($status === 'inactif') {
            $qb->andWhere('c.isActive = false');
        }

        $color = trim((string) ($filters['color'] ?? ''));
        if ($color !== '') {
            $qb->andWhere('LOWER(COALESCE(c.color, :emptyColor)) = :color')
                ->setParameter('emptyColor', '')
                ->setParameter('color', mb_strtolower($color));
        }

        $order = (string) ($filters['order'] ?? 'recent');
        if ($order === 'az') {
            $qb->orderBy('c.name', 'ASC');
        } elseif ($order === 'za') {
            $qb->orderBy('c.name', 'DESC');
        } elseif ($order === 'old') {
            $qb->orderBy('c.createdAt', 'ASC');
        } else {
            $qb->orderBy('c.createdAt', 'DESC');
        }

        return $qb;
    }

    public function findDistinctColors(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.color AS color')
            ->andWhere('c.color IS NOT NULL')
            ->andWhere("c.color <> ''")
            ->getQuery()
            ->getArrayResult();

        $colors = array_values(array_unique(array_filter(array_map(static fn (array $row) => strtolower((string) ($row['color'] ?? '')), $rows))));
        sort($colors);

        return $colors;
    }

    public function findAllOrderedByName(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }
}