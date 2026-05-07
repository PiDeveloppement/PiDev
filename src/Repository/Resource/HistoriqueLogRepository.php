<?php

namespace App\Repository\Resource;

use App\Entity\Resource\HistoriqueLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoriqueLog>
 *
 * @method HistoriqueLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method HistoriqueLog|null findOneBy(array<string, mixed> $criteria, array<string, string> $orderBy = null)
 * @method HistoriqueLog[]    findAll()
 * @method HistoriqueLog[]    findBy(array<string, mixed> $criteria, array<string, string> $orderBy = null, $limit = null, $offset = null)
 */
class HistoriqueLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueLog::class);
    }

    public function save(HistoriqueLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HistoriqueLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère les logs récents avec pagination
     * @param array{resourceType?: string, action?: string, user?: string, startDate?: string, endDate?: string} $filters
     * @return array<int, array{id: int, action: string, resource_type: string, resource_id: int, resource_name: string, old_values: ?string, new_values: ?string, created_at: string, ip_address: ?string, user_agent: ?string, user_name: ?string, user_email: ?string}>
     */
    public function findRecentWithPagination(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        
        $qb = $this->createQueryBuilder('h')
            ->select('h.id', 'h.action', 'h.resourceType as resource_type', 'h.resourceId as resource_id', 
                    'h.resourceName as resource_name', 'h.oldValues as old_values', 'h.newValues as new_values',
                    'h.createdAt as created_at', 'h.ipAddress as ip_address', 'h.userAgent as user_agent',
                    'CONCAT(u.firstName, \' \', u.lastName) as user_name', 'u.email as user_email')
            ->leftJoin('h.user', 'u')
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        // Appliquer les filtres
        if (isset($filters['resourceType']) && $filters['resourceType']) {
            $qb->andWhere('h.resourceType = :resourceType')
               ->setParameter('resourceType', $filters['resourceType']);
        }

        if (isset($filters['action']) && $filters['action']) {
            $qb->andWhere('h.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (isset($filters['user']) && $filters['user']) {
            $qb->andWhere('u.firstName LIKE :user OR u.lastName LIKE :user OR u.email LIKE :user')
               ->setParameter('user', '%' . $filters['user'] . '%');
        }

        if (isset($filters['startDate']) && $filters['startDate']) {
            $qb->andWhere('h.createdAt >= :startDate')
               ->setParameter('startDate', $filters['startDate']);
        }

        if (isset($filters['endDate']) && $filters['endDate']) {
            $qb->andWhere('h.createdAt <= :endDate')
               ->setParameter('endDate', $filters['endDate']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les logs selon les filtres
     * @param array{resourceType?: string, action?: string, user?: string, startDate?: string, endDate?: string} $filters
     */
    public function countByFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->leftJoin('h.user', 'u');

        // Appliquer les filtres
        if (isset($filters['resourceType']) && $filters['resourceType']) {
            $qb->andWhere('h.resourceType = :resourceType')
               ->setParameter('resourceType', $filters['resourceType']);
        }

        if (isset($filters['action']) && $filters['action']) {
            $qb->andWhere('h.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (isset($filters['user']) && $filters['user']) {
            $qb->andWhere('u.firstName LIKE :user OR u.lastName LIKE :user OR u.email LIKE :user')
               ->setParameter('user', '%' . $filters['user'] . '%');
        }

        if (isset($filters['startDate']) && $filters['startDate']) {
            $qb->andWhere('h.createdAt >= :startDate')
               ->setParameter('startDate', $filters['startDate']);
        }

        if (isset($filters['endDate']) && $filters['endDate']) {
            $qb->andWhere('h.createdAt <= :endDate')
               ->setParameter('endDate', $filters['endDate']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les logs pour une ressource spécifique
     * @return array<int, HistoriqueLog>
     */
    public function findByResource(string $resourceType, int $resourceId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.resourceType = :resourceType')
            ->andWhere('h.resourceId = :resourceId')
            ->setParameter('resourceType', $resourceType)
            ->setParameter('resourceId', $resourceId)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}