<?php

namespace App\Repository\User;


use App\Entity\User\UserModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @extends ServiceEntityRepository<UserModel>
 *
 * @method UserModel|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserModel|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserModel[]    findAll()
 * @method UserModel[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserModel::class);
    }

    // ==================== MÉTHODES DE BASE ====================

    /**
     * Récupère tous les utilisateurs avec leurs rôles
     * Équivalent de getAllUsers() dans UserService.java
     */
    public function findAllWithRoles(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère un utilisateur par email
     * Équivalent de getUserByEmail() dans UserService.java
     */
    public function findByEmail(string $email): ?UserModel
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère un utilisateur par ID avec son rôle
     * Équivalent de getUserById() dans UserService.java
     */
    public function findByIdWithRole(int $id): ?UserModel
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r')
            ->andWhere('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ==================== MÉTHODES DE RECHERCHE AVANCÉE ====================

public function searchUsers(
    ?string $keyword = null,
    ?string $faculte = null,
    ?string $role = null,
    int $page = 1,
    int $limit = 5,
    ?string $sortBy = null,
    ?string $sortOrder = null
): array {
    $qb = $this->createQueryBuilder('u')
        ->leftJoin('u.role', 'r')
        ->addSelect('r');

    // Filtre par mot-clé (prénom, nom, email)
    if ($keyword && $keyword !== '') {
        $qb->andWhere('u.firstName LIKE :keyword OR u.lastName LIKE :keyword OR u.email LIKE :keyword')
           ->setParameter('keyword', '%' . $keyword . '%');
    }

    // Filtre par faculté
    if ($faculte && $faculte !== '') {
        $qb->andWhere('u.faculte = :faculte')
           ->setParameter('faculte', $faculte);
    }

    // Filtre par rôle
    if ($role && $role !== '') {
        $qb->andWhere('r.roleName = :role')
           ->setParameter('role', $role);
    }

    // Tri dynamique
    $sortBy = $sortBy ?: 'id';
    $sortOrder = $sortOrder ?: 'DESC';
    
    // Validation du champ de tri pour éviter les injections SQL
    $allowedSortFields = ['id', 'firstName', 'lastName', 'email', 'faculte', 'registrationDate'];
    if (!in_array($sortBy, $allowedSortFields)) {
        $sortBy = 'id';
    }
    
    $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

    // Pagination
    $qb->setFirstResult(($page - 1) * $limit)
       ->setMaxResults($limit)
       ->orderBy('u.' . $sortBy, $sortOrder);

    return $qb->getQuery()->getResult();
}
public function countUsers(
    ?string $keyword = null,
    ?string $faculte = null,
    ?string $role = null
): int {
    $qb = $this->createQueryBuilder('u')
        ->select('COUNT(u.id)')
        ->leftJoin('u.role', 'r');

    // Filtre par mot-clé
    if ($keyword && $keyword !== '') {
        $qb->andWhere('u.firstName LIKE :keyword OR u.lastName LIKE :keyword OR u.email LIKE :keyword')
           ->setParameter('keyword', '%' . $keyword . '%');
    }

    // Filtre par faculté
    if ($faculte && $faculte !== '') {
        $qb->andWhere('u.faculte = :faculte')
           ->setParameter('faculte', $faculte);
    }

    // Filtre par rôle
    if ($role && $role !== '') {
        $qb->andWhere('r.roleName = :role')
           ->setParameter('role', $role);
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
}

    // ==================== MÉTHODES DE STATISTIQUES ====================

    /**
     * Compte le nombre total d'utilisateurs
     * Équivalent de getTotalParticipantsCount() dans UserService.java
     */
    public function countAll(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère le nombre d'utilisateurs par rôle
     * Équivalent de getUsersCountByRole() dans UserService.java
     */
    public function countByRole(): array
    {
        return $this->createQueryBuilder('u')
            ->select('r.roleName as role, COUNT(u.id) as count')
            ->leftJoin('u.role', 'r')
            ->groupBy('r.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère le nombre de nouveaux utilisateurs ce mois
     * Équivalent de getNewUsersThisMonthCount() dans UserService.java
     */
    public function countNewThisMonth(): int
    {
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        $endOfMonth = new \DateTime('last day of this month 23:59:59');

        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.registrationDate BETWEEN :start AND :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les utilisateurs récents
     * Équivalent de la logique dans UserController pour l'affichage
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r')
            ->orderBy('u.registrationDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ==================== MÉTHODES DE FILTRES SPÉCIFIQUES ====================

  /**
 * Récupère toutes les facultés distinctes
 */
public function findAllFacultes(): array
{
    $result = $this->createQueryBuilder('u')
        ->select('DISTINCT u.faculte')
        ->where('u.faculte IS NOT NULL')
        ->andWhere('u.faculte != \'\'')
        ->orderBy('u.faculte', 'ASC')
        ->getQuery()
        ->getResult();
    
    // Convertir le résultat en tableau simple de strings
    return array_map(function($item) {
        return $item['faculte'];
    }, $result);
}

    /**
     * Récupère les utilisateurs par rôle (nom du rôle)
     * Équivalent de getUsersByRole() dans UserService.java
     */
    public function findByRoleName(string $roleName): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r')
            ->andWhere('r.roleName LIKE :roleName')
            ->setParameter('roleName', '%' . $roleName . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les emails des administrateurs
     * Équivalent de getAllAdminEmails() dans UserService.java
     */
    public function findAdminEmails(): array
    {
        $result = $this->createQueryBuilder('u')
            ->select('u.email')
            ->leftJoin('u.role', 'r')
            ->andWhere('LOWER(r.roleName) = :role')
            ->setParameter('role', 'admin')
            ->getQuery()
            ->getSingleColumnResult();

        return !empty($result) ? $result : [];
    }

    // ==================== MÉTHODES POUR LE TÉLÉPHONE ====================

    /**
     * Récupère un utilisateur par numéro de téléphone
     * Équivalent de getUserByPhone() dans UserService.java
     */
    public function findByPhone(string $phone): ?UserModel
    {
        $normalizedPhone = $this->normalizePhoneNumber($phone);
        $phoneWithoutPlus = str_replace('+', '', $normalizedPhone);
        $phoneWithoutSpaces = str_replace(' ', '', $normalizedPhone);
        $phoneWithoutPlusAndSpaces = str_replace(['+', ' '], '', $normalizedPhone);

        return $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r')
            ->andWhere('u.phone = :phone1 OR u.phone = :phone2 OR u.phone = :phone3 OR u.phone = :phone4 OR u.phone LIKE :phone5')
            ->setParameter('phone1', $normalizedPhone)
            ->setParameter('phone2', $phoneWithoutPlus)
            ->setParameter('phone3', $phoneWithoutSpaces)
            ->setParameter('phone4', $phoneWithoutPlusAndSpaces)
            ->setParameter('phone5', '%' . $phoneWithoutPlusAndSpaces . '%')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un numéro de téléphone existe
     * Équivalent de isPhoneNumberExists() dans UserService.java
     */
    public function isPhoneExists(string $phone): bool
    {
        $phoneWithoutPlus = str_replace('+', '', $phone);

        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.phone = :phone1 OR u.phone = :phone2')
            ->setParameter('phone1', $phone)
            ->setParameter('phone2', $phoneWithoutPlus);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Récupère les utilisateurs ayant un téléphone
     * Équivalent de getUsersWithPhone() dans UserService.java
     */
    public function findUsersWithPhone(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r')
            ->andWhere('u.phone IS NOT NULL')
            ->andWhere('u.phone != :empty')
            ->setParameter('empty', '')
            ->orderBy('u.registrationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ==================== MÉTHODES D'AUTHENTIFICATION ====================

    /**
     * Authentification utilisateur
     * Équivalent de authenticate() dans UserService.java
     * Note: Symfony gère l'auth via Security, mais on garde pour compatibilité
     */
    public function authenticate(string $email, string $password): ?UserModel
    {
        // Note: Symfony utilise password_hash(), donc cette méthode est rarement utilisée directement
        return $this->findByEmail($email);
    }

    /**
     * Vérifie si un email existe
     * Équivalent de isEmailExists() dans UserService.java
     */
    public function isEmailExists(string $email): bool
    {
        return null !== $this->findOneBy(['email' => $email]);
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Normalise un numéro de téléphone
     * Équivalent de normalizePhoneNumber() dans UserService.java
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Enlever tous les caractères non numériques sauf le +
        $normalized = preg_replace('/[^0-9+]/', '', $phone);

        // Format tunisien
        if (preg_match('/^0[0-9]{7}$/', $normalized)) {
            $normalized = '+216' . substr($normalized, 1);
        } elseif (preg_match('/^216[0-9]{8}$/', $normalized)) {
            $normalized = '+' . $normalized;
        } elseif (preg_match('/^[0-9]{8}$/', $normalized)) {
            $normalized = '+216' . $normalized;
        }

        return $normalized;
    }

    /**
     * Pagination : récupère une page d'utilisateurs
     * Équivalent de la logique de pagination dans UserController.java
     */

public function findPage(int $page, int $limit = 5): array
{
    return $this->createQueryBuilder('u')
        ->leftJoin('u.role', 'r')
        ->addSelect('r')
        ->orderBy('u.id', 'DESC')
        ->setFirstResult(($page - 1) * $limit)  // ← Utilise $page, pas un chiffre
        ->setMaxResults($limit)                 // ← Utilise $limit, pas un chiffre
        ->getQuery()
        ->getResult();
}

    /**
     * Récupère les utilisateurs pour l'export PDF
     * Similaire à la logique d'export dans votre EventListController
     */
    public function findForExport(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r')
            ->orderBy('u.registrationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
}