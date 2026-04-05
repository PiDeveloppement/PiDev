<?php

namespace App\Service\User;

use App\Entity\User\UserModel;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {}

    // ==================== CRUD PRINCIPAL ====================

    /**
     * Récupère tous les utilisateurs
     * Équivalent de getAllUsers()
     */
    public function getAllUsers(): array
    {
        return $this->userRepository->findAllWithRoles();
    }

    /**
     * Récupère un utilisateur par ID
     * Équivalent de getUserById()
     */
    public function getUserById(int $id): ?UserModel
    {
        return $this->userRepository->findByIdWithRole($id);
    }

    /**
     * Récupère un utilisateur par email
     * Équivalent de getUserByEmail()
     */
    public function getUserByEmail(string $email): ?UserModel
    {
        return $this->userRepository->findByEmail($email);
    }

    /**
     * Crée un nouvel utilisateur
     * Équivalent de registerUser()
     */
   // src/Service/User/UserService.php

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
    if ($user->getRoleId() === null) {
        $this->logger->error('Tentative de création d\'utilisateur sans rôle');
        throw new \Exception('Le rôle est obligatoire');
    }

    // Vérifier que l'objet Role est aussi défini
    if ($user->getRole() === null && $user->getRoleId()) {
        $role = $this->getRoleById($user->getRoleId());
        $user->setRole($role);
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
     * Équivalent de updateUser()
     */
    public function updateUser(UserModel $user, ?string $newPassword = null): UserModel
    {
        // Mettre à jour le mot de passe si fourni
        if ($newPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
        }

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Supprime un utilisateur
     * Équivalent de deleteUser()
     */
    public function deleteUser(int $id): bool
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return false;
        }

        try {
            // Supprimer les tokens associés (géré par cascade)
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur suppression utilisateur: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== AUTHENTIFICATION ====================

    /**
     * Authentifie un utilisateur
     * Équivalent de authenticate()
     * Note: Utilisé principalement pour compatibilité, Symfony gère l'auth via Security
     */
    public function authenticate(string $email, string $password): ?UserModel
    {
        $user = $this->getUserByEmail($email);
        
        if (!$user) {
            return null;
        }

        // Vérifier le mot de passe (pour compatibilité avec ancien système)
        if ($this->passwordHasher->isPasswordValid($user, $password)) {
            return $user;
        }

        return null;
    }

    /**
     * Vérifie si un email existe
     * Équivalent de isEmailExists()
     */
    public function isEmailExists(string $email): bool
    {
        return $this->userRepository->isEmailExists($email);
    }

    // ==================== STATISTIQUES ====================

    /**
     * Compte le nombre total d'utilisateurs
     * Équivalent de getTotalParticipantsCount()
     */
    public function getTotalUsersCount(): int
    {
        return $this->userRepository->countAll();
    }

    /**
     * Compte le nombre de nouveaux utilisateurs ce mois
     * Équivalent de getNewUsersThisMonthCount()
     */
    public function getNewUsersThisMonthCount(): int
    {
        return $this->userRepository->countNewThisMonth();
    }

    /**
     * Récupère les statistiques par rôle
     * Équivalent de getUsersCountByRole()
     */
    public function getUsersCountByRole(): array
    {
        return $this->userRepository->countByRole();
    }

    // ==================== FILTRES ET RECHERCHE ====================

    /**
     * Récupère toutes les facultés distinctes
     * Équivalent de getAllFacultes()
     */
    public function getAllFacultes(): array
    {
        return $this->userRepository->findAllFacultes();
    }

    /**
     * Récupère les utilisateurs par rôle
     * Équivalent de getUsersByRole()
     */
    public function getUsersByRole(string $roleName): array
    {
        return $this->userRepository->findByRoleName($roleName);
    }

    /**
     * Récupère les emails des administrateurs
     * Équivalent de getAllAdminEmails()
     */
    public function getAdminEmails(): array
    {
        return $this->userRepository->findAdminEmails();
    }

    /**
     * Recherche avancée d'utilisateurs
     * Équivalent de applyFilters() dans le contrôleur
     */
    public function searchUsers(
        ?string $keyword = null,
        ?string $faculte = null,
        ?string $role = null,
        int $page = 1,
        int $limit = 5
    ): array {
        return $this->userRepository->searchUsers($keyword, $faculte, $role, 'id', 'DESC', $page, $limit);
    }

    /**
     * Compte les utilisateurs avec filtres
     */
    public function countUsers(
        ?string $keyword = null,
        ?string $faculte = null,
        ?string $role = null
    ): int {
        return $this->userRepository->countUsers($keyword, $faculte, $role);
    }

    // ==================== GESTION DU TÉLÉPHONE ====================

    /**
     * Récupère un utilisateur par téléphone
     * Équivalent de getUserByPhone()
     */
    public function getUserByPhone(string $phone): ?UserModel
    {
        return $this->userRepository->findByPhone($phone);
    }

    /**
     * Vérifie si un numéro existe
     * Équivalent de isPhoneNumberExists()
     */
    public function isPhoneExists(string $phone): bool
    {
        return $this->userRepository->isPhoneExists($phone);
    }

    /**
     * Récupère les utilisateurs avec téléphone
     * Équivalent de getUsersWithPhone()
     */
    public function getUsersWithPhone(): array
    {
        return $this->userRepository->findUsersWithPhone();
    }

    /**
     * Met à jour le téléphone d'un utilisateur
     * Équivalent de updateUserPhone()
     */
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

    /**
     * Récupère une page d'utilisateurs
     * Équivalent de la logique de pagination dans UserController
     */
    public function getUsersPage(int $page, int $limit = 5): array
    {
        return $this->userRepository->findPage($page, $limit);
    }

    /**
     * Récupère les utilisateurs récents
     */
    public function getRecentUsers(int $limit = 10): array
    {
        return $this->userRepository->findRecent($limit);
    }
    /**
 * Récupère un rôle par son ID
 */
public function getRoleById(int $id): ?\App\Entity\Role\Role
{
    return $this->entityManager->getRepository(\App\Entity\Role\Role::class)->find($id);
}
}