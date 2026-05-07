<?php

namespace App\Repository\Depense;

use App\Entity\Depense\Depense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Depense>
 */
class DepenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // Repository Doctrine standard pour les operations CRUD et filtres custom sur les depenses.
        parent::__construct($registry, Depense::class);
    }
}