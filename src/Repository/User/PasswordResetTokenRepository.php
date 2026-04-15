<?php

namespace App\Repository\User;

use App\Entity\User\PasswordResetToken;

use App\Entity\User\UserModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    /**
     * Trouve un token valide par sa valeur
     */
    public function findValidToken(string $token): ?PasswordResetToken
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.token = :token')
            ->andWhere('t.used = :used')
            ->andWhere('t.expiryDate > :now')
            ->setParameter('token', $token)
            ->setParameter('used', false)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les tokens valides pour un utilisateur
     */
    public function findValidByUser(UserModel|int $user): array
    {
        $userId = $user instanceof UserModel ? $user->getId() : $user;
        
        return $this->createQueryBuilder('t')
            ->andWhere('t.userId = :userId')
            ->andWhere('t.used = :used')
            ->andWhere('t.expiryDate > :now')
            ->setParameter('userId', $userId)
            ->setParameter('used', false)
            ->setParameter('now', new \DateTime())
            ->orderBy('t.expiryDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le dernier token valide pour un utilisateur
     */
    public function findLatestValidByUser(UserModel|int $user): ?PasswordResetToken
    {
        $userId = $user instanceof UserModel ? $user->getId() : $user;
        
        return $this->createQueryBuilder('t')
            ->andWhere('t.userId = :userId')
            ->andWhere('t.used = :used')
            ->andWhere('t.expiryDate > :now')
            ->setParameter('userId', $userId)
            ->setParameter('used', false)
            ->setParameter('now', new \DateTime())
            ->orderBy('t.expiryDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les tokens expirés
     */
    public function findExpiredTokens(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.expiryDate <= :now')
            ->orWhere('t.used = :used')
            ->setParameter('now', new \DateTime())
            ->setParameter('used', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tokens par utilisateur (tous, même expirés)
     */
    public function findByUser(UserModel|int $user): array
    {
        $userId = $user instanceof UserModel ? $user->getId() : $user;
        
        return $this->createQueryBuilder('t')
            ->andWhere('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de tokens valides pour un utilisateur
     */
    public function countValidByUser(UserModel|int $user): int
    {
        $userId = $user instanceof UserModel ? $user->getId() : $user;
        
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.userId = :userId')
            ->andWhere('t.used = :used')
            ->andWhere('t.expiryDate > :now')
            ->setParameter('userId', $userId)
            ->setParameter('used', false)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Supprime tous les tokens expirés
     */
    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiryDate <= :now')
            ->orWhere('t.used = :used')
            ->setParameter('now', new \DateTime())
            ->setParameter('used', true)
            ->getQuery()
            ->execute();
    }

    /**
     * Désactive tous les tokens d'un utilisateur
     */
    public function deactivateAllForUser(UserModel|int $user): int
    {
        $userId = $user instanceof UserModel ? $user->getId() : $user;
        
        return $this->createQueryBuilder('t')
            ->update()
            ->set('t.used', ':used')
            ->where('t.userId = :userId')
            ->setParameter('used', true)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}