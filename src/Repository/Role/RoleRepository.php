<?php
// src/Repository/RoleRepository.php

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
}