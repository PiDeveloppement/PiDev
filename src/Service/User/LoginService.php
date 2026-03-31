<?php

namespace App\Service\User;


use App\Entity\User\UserModel;
use App\Repository\User\UserRepository;

use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger
    ) {
        $this->logger->info('🔐 LoginService initialisé');
    }

    /**
     * Vérifie si les informations de connexion sont valides
     * Compatible avec votre base de données actuelle (mots de passe en clair ou hashés)
     */
    public function authenticate(string $email, string $password): ?UserModel
    {
        try {
            $this->logger->info('🔐 Tentative de connexion pour: ' . $email);

            // Récupérer l'utilisateur par email
            $user = $this->userRepository->findOneBy(['email' => trim($email)]);

            if (!$user) {
                $this->logger->warning('❌ Utilisateur non trouvé: ' . $email);
                return null;
            }

            // Vérification du mot de passe
            // 🔄 Compatibilité avec votre système actuel (JavaFX)
            if ($this->verifyPassword($user, trim($password))) {
                $this->logger->info('✅ Authentification réussie pour: ' . $email);
                return $user;
            }

            $this->logger->warning('❌ Mot de passe incorrect pour: ' . $email);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur lors de l\'authentification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifie le mot de passe selon la méthode utilisée
     * Supporte à la fois les mots de passe en clair (JavaFX) et hashés (Symfony)
     */
    private function verifyPassword(UserModel $user, string $plainPassword): bool
    {
        $hashedPassword = $user->getPassword();

        // Si le mot de passe est déjà hashé (format Symfony)
        if ($this->isPasswordHashed($hashedPassword)) {
            return $this->passwordHasher->isPasswordValid($user, $plainPassword);
        }

        // Sinon, comparaison en clair (compatible avec votre base JavaFX actuelle)
        return $hashedPassword === $plainPassword;
    }

    /**
     * Détecte si un mot de passe est hashé au format Symfony
     */
    private function isPasswordHashed(string $password): bool
    {
        // Les hash Symfony commencent par $2y$, $argon2i$, etc.
        return str_starts_with($password, '$2y$') || 
               str_starts_with($password, '$argon2i$') ||
               strlen($password) > 30; // Heuristique simple
    }

    /**
     * Migre un mot de passe en clair vers un hash Symfony
     * Utile pour transition progressive
     */
    public function migratePassword(UserModel $user, string $plainPassword): void
    {
        if (!$this->isPasswordHashed($user->getPassword())) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            
            $this->logger->info('🔄 Mot de passe migré pour: ' . $user->getEmail());
        }
    }

    /**
     * Récupère le rôle de l'utilisateur (compatible avec votre système)
     */
    public function getUserRole(UserModel $user): ?string
    {
        $role = $user->getRole();
        return $role?->getRoleName();
    }

    /**
     * Récupère le rôle ID (pour compatibilité avec JavaFX)
     */
    public function getUserRoleId(UserModel $user): ?int
    {
        return $user->getRoleId();
    }
}