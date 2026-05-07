<?php

namespace App\Repository\Resource;

use App\Entity\Resource\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipement>
 */
class EquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipement::class);
    }

    public function searchByTermAndCategory(?string $term, ?string $category): array
    {
        $qb = $this->createQueryBuilder('e');
        if ($term) {
            $qb->andWhere('e.name LIKE :t')->setParameter('t', '%'.$term.'%');
        }
        if ($category && $category !== 'Toutes les catégories') {
            $qb->andWhere('e.equipement_type = :c')->setParameter('c', $category);
        }
        return $qb->getQuery()->getResult();
    }

public function findWithFilters(?string $category, ?string $term): array
{
    $qb = $this->createQueryBuilder('e');

    if ($category) {
        // Correction ici : equipement_type au lieu de equipementType
        $qb->andWhere('e.equipement_type = :cat')
           ->setParameter('cat', $category);
    }

    if ($term) {
        $qb->andWhere('e.name LIKE :term OR e.id LIKE :term')
           ->setParameter('term', '%' . $term . '%');
    }

    return $qb->orderBy('e.id', 'DESC')->getQuery()->getResult();
}

public function findAllUniqueCategories(): array
{
    $results = $this->createQueryBuilder('e')
        ->select('DISTINCT e.equipement_type')
        ->where('e.equipement_type IS NOT NULL')
        ->getQuery()
        ->getResult();

    // Transforme le tableau [['equipement_type' => 'val']] en ['val']
    return array_column($results, 'equipement_type');
}

//    /**
//     * @return Equipement[] Returns an array of Equipement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Equipement
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}