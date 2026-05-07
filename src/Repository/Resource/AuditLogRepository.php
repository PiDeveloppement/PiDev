<?php

namespace App\Repository\Resource;

use App\Entity\Resource\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 *
 * @method AuditLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuditLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method AuditLog[]    findAll()
 * @method AuditLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function save(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère les logs d'audit pour une ressource spécifique
     */
    public function findByResource(string $resourceType, int $resourceId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.resourceType = :resourceType')
            ->andWhere('a.resourceId = :resourceId')
            ->setParameter('resourceType', $resourceType)
            ->setParameter('resourceId', $resourceId)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les logs d'audit pour un type de ressource
     */
    public function findByResourceType(string $resourceType, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.resourceType = :resourceType')
            ->setParameter('resourceType', $resourceType)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les logs récents avec pagination
     */
    public function findRecentLogs(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de logs
     */
    public function countTotalLogs(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche les logs par utilisateur
     */
    public function findByUser(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les logs par plage de dates
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.createdAt >= :startDate')
            ->andWhere('a.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}