<?php

namespace App\Service\User;

use App\Entity\User\UserModel;
use App\Entity\Role\Role;
use App\Repository\User\UserRepository;
use App\Repository\Role\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {}

    // ==================== CRUD PRINCIPAL ====================

    /**
     * Récupère tous les utilisateurs
     */
    public function getAllUsers(): array
    {
        return $this->userRepository->findAllWithRoles();
    }

    /**
     * Récupère un utilisateur par ID
     */
    public function getUserById(int $id): ?UserModel
    {
        return $this->userRepository->findByIdWithRole($id);
    }

    /**
     * Récupère un utilisateur par email
     */
    public function getUserByEmail(string $email): ?UserModel
    {
        return $this->userRepository->findByEmail($email);
    }

    /**
     * Crée un nouvel utilisateur
     */
    public function createUser(UserModel $user, ?string $plainPassword = null): UserModel
    {
        // Hasher le mot de passe si fourni
        if ($plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }

        // Définir la date d'inscription
        if (!$user->getRegistrationDate()) {
            $user->setRegistrationDate(new \DateTime());
        }

        // Vérifier que le rôle n'est pas null
        if ($user->getRoleId() === null && $user->getRole() === null) {
            $this->logger->error('Tentative de création d\'utilisateur sans rôle');
            throw new \Exception('Le rôle est obligatoire');
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Envoyer email de bienvenue
        try {
            $this->emailService->sendWelcomeEmail($user);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email bienvenue: ' . $e->getMessage());
        }

        return $user;
    }

    /**
     * Met à jour un utilisateur
     */
    public function updateUser(UserModel $user, ?string $newPassword = null): UserModel
    {
        if ($newPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
        }

        $this->entityManager->flush();
        return $user;
    }

    /**
     * Supprime un utilisateur
     */
    public function deleteUser(int $id): bool
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return false;
        }

        try {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur suppression utilisateur: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== AUTHENTIFICATION ====================

    public function authenticate(string $email, string $password): ?UserModel
    {
        $user = $this->getUserByEmail($email);
        
        if (!$user) {
            return null;
        }

        if ($this->passwordHasher->isPasswordValid($user, $password)) {
            return $user;
        }

        return null;
    }

    public function isEmailExists(string $email): bool
    {
        return $this->userRepository->isEmailExists($email);
    }

    // ==================== STATISTIQUES ====================

    public function getTotalUsersCount(): int
    {
        return $this->userRepository->countAll();
    }

    public function getNewUsersThisMonthCount(): int
    {
        return $this->userRepository->countNewThisMonth();
    }

    public function getUsersCountByRole(): array
    {
        return $this->userRepository->countByRole();
    }

    // ==================== FILTRES ET RECHERCHE ====================

    public function getAllFacultes(): array
    {
        return $this->userRepository->findAllFacultes();
    }

    public function getUsersByRole(string $roleName): array
    {
        return $this->userRepository->findByRoleName($roleName);
    }

    public function getAdminEmails(): array
    {
        return $this->userRepository->findAdminEmails();
    }

    /**
     * Recherche avancée d'utilisateurs (pour le contrôleur)
     */
    public function searchUsers(
        ?string $keyword = null,
        ?string $faculte = null,
        ?string $role = null,
        int $page = 1,
        int $limit = 5
    ): array {
        return $this->userRepository->searchUsers($keyword, $faculte, $role, $page, $limit);
    }

    /**
     * Compte les utilisateurs avec filtres (pour le contrôleur)
     */
    public function countUsers(
        ?string $keyword = null,
        ?string $faculte = null,
        ?string $role = null
    ): int {
        return $this->userRepository->countUsers($keyword, $faculte, $role);
    }

    // ==================== GESTION DU TÉLÉPHONE ====================

    public function getUserByPhone(string $phone): ?UserModel
    {
        return $this->userRepository->findByPhone($phone);
    }

    public function isPhoneExists(string $phone): bool
    {
        return $this->userRepository->isPhoneExists($phone);
    }

    public function getUsersWithPhone(): array
    {
        return $this->userRepository->findUsersWithPhone();
    }

    public function updateUserPhone(int $userId, string $phone): bool
    {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }

        $user->setPhone($phone);
        $this->entityManager->flush();
        return true;
    }

    // ==================== PAGINATION ====================

    public function getUsersPage(int $page, int $limit = 5): array
    {
        return $this->userRepository->findPage($page, $limit);
    }

    public function getRecentUsers(int $limit = 10): array
    {
        return $this->userRepository->findRecent($limit);
    }

    /**
     * Récupère un rôle par son ID
     */
    public function getRoleById(int $id): ?Role
    {
        return $this->roleRepository->find($id);
    }
}