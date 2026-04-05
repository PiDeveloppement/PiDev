<?php

namespace App\Repository\Role;

use App\Entity\Role\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.roleName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByName(string $roleName): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.roleName = :name')
            ->setParameter('name', $roleName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllNames(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.roleName')
            ->orderBy('r.roleName', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPage(int $page, int $limit): array
    {
        return $this->createQueryBuilder('r')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('r.roleName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les rôles par nom avec pagination
     */
    public function searchByName(string $keyword, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('r');
        
        if (!empty($keyword)) {
            $qb->where('r.roleName LIKE :keyword')
               ->setParameter('keyword', '%' . $keyword . '%');
        }
        
        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->orderBy('r.roleName', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte le nombre de résultats de recherche
     */
    public function countSearchResults(string $keyword): int
    {
        $qb = $this->createQueryBuilder('r')
                   ->select('COUNT(r.id)');
        
        if (!empty($keyword)) {
            $qb->where('r.roleName LIKE :keyword')
               ->setParameter('keyword', '%' . $keyword . '%');
        }
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getUsageStatistics(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.roleName, COUNT(u.id) as userCount')
            ->leftJoin('r.users', 'u')
            ->groupBy('r.id')
            ->getQuery()
            ->getResult();
    }

    public function findIdByName(string $roleName): ?int
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.id')
            ->where('r.roleName = :name')
            ->setParameter('name', $roleName)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['id'] : null;
    }
}