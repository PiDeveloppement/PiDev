<?php

namespace App\Service\User;

use App\Entity\User\UserModel;
use App\Entity\User\PasswordResetToken;

use App\Repository\User\PasswordResetTokenRepository;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class PasswordResetService
{
    private ?string $currentToken = null;
    private ?int $currentUserId = null;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PasswordResetTokenRepository $tokenRepository,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger,
        private EmailService $emailService
    ) {
        $this->logger->info('✅ PasswordResetService initialisé');
        
        // Nettoyer les tokens expirés au démarrage
        $this->cleanExpiredTokens();
    }

    // ========== GESTION DES TOKENS ==========

    /**
     * Crée un nouveau token de réinitialisation
     */
    public function createToken(UserModel $user): PasswordResetToken
    {
        try {
            // Désactiver les anciens tokens
            $this->deactivateExistingTokens($user);

            // Créer un nouveau token
            $token = new PasswordResetToken();
            $token->setToken(Uuid::v4()->toRfc4122());
            $token->setUser($user);
            $token->setExpiryDate(new \DateTime('+1 hour'));
            $token->setUsed(false);

            $this->entityManager->persist($token);
            $this->entityManager->flush();

            $this->logger->info('✅ Token créé pour l\'utilisateur ID: ' . $user->getId());
            $this->logger->debug('   Token: ' . $token->getToken());
            $this->logger->debug('   Expire le: ' . $token->getExpiryDate()->format('Y-m-d H:i:s'));

            return $token;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur createToken: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Désactive les tokens existants pour un utilisateur
     */
    private function deactivateExistingTokens(UserModel $user): void
    {
        $activeTokens = $this->tokenRepository->findActiveByUser($user->getId());
        
        foreach ($activeTokens as $token) {
            $token->setUsed(true);
            $this->entityManager->persist($token);
        }
        
        $this->entityManager->flush();
        
        if (count($activeTokens) > 0) {
            $this->logger->info('🔄 ' . count($activeTokens) . ' anciens tokens désactivés');
        }
    }

    /**
     * Valide un token
     */
    public function validateToken(string $token): bool
    {
        try {
            if (empty($token)) {
                $this->logger->warning('❌ validateToken: Token vide');
                return false;
            }

            $resetToken = $this->tokenRepository->findValidToken($token);

            if ($resetToken) {
                $this->currentToken = $token;
                $this->currentUserId = $resetToken->getUser()->getId();
                
                $this->logger->info('✅ Token valide pour l\'utilisateur ID: ' . $this->currentUserId);
                return true;
            }

            $this->logger->warning('❌ Token invalide ou expiré: ' . $token);
            return false;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur validateToken: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère l'entité token à partir du token string
     */
    private function getTokenEntity(string $token): ?PasswordResetToken
    {
        return $this->tokenRepository->findOneBy(['token' => $token]);
    }

    /**
     * Marque un token comme utilisé
     */
    private function markTokenAsUsed(string $token): bool
    {
        try {
            $resetToken = $this->getTokenEntity($token);
            
            if (!$resetToken) {
                $this->logger->warning('❌ Token non trouvé: ' . $token);
                return false;
            }

            $resetToken->setUsed(true);
            $this->entityManager->flush();

            $this->logger->info('✅ Token marqué comme utilisé: ' . $token);
            return true;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur markTokenAsUsed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Nettoie les tokens expirés
     */
    public function cleanExpiredTokens(): int
    {
        try {
            $expiredTokens = $this->tokenRepository->findExpiredTokens();
            
            foreach ($expiredTokens as $token) {
                $this->entityManager->remove($token);
            }
            
            $this->entityManager->flush();

            $count = count($expiredTokens);
            if ($count > 0) {
                $this->logger->info('🧹 Nettoyage: ' . $count . ' tokens expirés supprimés');
            }

            return $count;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur cleanExpiredTokens: ' . $e->getMessage());
            return 0;
        }
    }

    // ========== GESTION DES MOTS DE PASSE ==========

    /**
     * Réinitialise le mot de passe (version sécurisée avec hashage)
     */
    public function resetPassword(string $newPassword): bool
    {
        try {
            if (!$this->currentToken || !$this->currentUserId) {
                $this->logger->error('❌ resetPassword: Aucun token valide en session');
                return false;
            }

            // Vérifier une dernière fois que le token est valide
            if (!$this->validateToken($this->currentToken)) {
                $this->logger->error('❌ resetPassword: Token expiré ou déjà utilisé');
                return false;
            }

            // Récupérer l'utilisateur
            $user = $this->userRepository->find($this->currentUserId);
            
            if (!$user) {
                $this->logger->error('❌ resetPassword: Utilisateur non trouvé ID: ' . $this->currentUserId);
                return false;
            }

            // Hasher le nouveau mot de passe (sécurisé)
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            // Marquer le token comme utilisé
            $this->markTokenAsUsed($this->currentToken);

            $this->entityManager->flush();

            $this->logger->info('✅ Mot de passe réinitialisé pour l\'utilisateur ID: ' . $this->currentUserId);
            
            // Réinitialiser les variables de session
            $this->currentToken = null;
            $this->currentUserId = null;

            return true;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur resetPassword: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Version NON sécurisée pour compatibilité avec JavaFX (mots de passe en clair)
     * ⚠️ À UTILISER UNIQUEMENT POUR LA MIGRATION
     */
    public function resetPasswordInsecure(string $newPassword): bool
    {
        try {
            if (!$this->currentToken || !$this->currentUserId) {
                $this->logger->error('❌ resetPasswordInsecure: Aucun token valide');
                return false;
            }

            if (!$this->validateToken($this->currentToken)) {
                return false;
            }

            $user = $this->userRepository->find($this->currentUserId);
            
            if (!$user) {
                return false;
            }

            // ⚠️ Stockage en clair (NON SÉCURISÉ - pour migration uniquement)
            $user->setPassword($newPassword); 

            $this->markTokenAsUsed($this->currentToken);
            $this->entityManager->flush();

            $this->logger->warning('⚠️ ATTENTION: Mot de passe stocké EN CLAIR pour l\'utilisateur ID: ' . $this->currentUserId);
            
            $this->currentToken = null;
            $this->currentUserId = null;

            return true;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur resetPasswordInsecure: ' . $e->getMessage());
            return false;
        }
    }

    // ========== MÉTHODES UTILITAIRES ==========

    /**
     * Génère un token unique
     */
    public function generateToken(): string
    {
        return Uuid::v4()->toRfc4122();
    }

    /**
     * Récupère l'ID utilisateur associé à un token
     */
    public function getUserIdFromToken(string $token): ?int
    {
        try {
            $resetToken = $this->tokenRepository->findOneBy(['token' => $token]);
            return $resetToken?->getUser()->getId();
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur getUserIdFromToken: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifie si un token existe
     */
    public function tokenExists(string $token): bool
    {
        try {
            return $this->tokenRepository->count(['token' => $token]) > 0;
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur tokenExists: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un token
     */
    public function deleteToken(string $token): bool
    {
        try {
            $resetToken = $this->getTokenEntity($token);
            
            if (!$resetToken) {
                return false;
            }

            $this->entityManager->remove($resetToken);
            $this->entityManager->flush();

            $this->logger->info('✅ Token supprimé: ' . $token);
            return true;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur deleteToken: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère le nombre de tokens actifs pour un utilisateur
     */
    public function getActiveTokensCount(int $userId): int
    {
        try {
            return count($this->tokenRepository->findActiveByUser($userId));
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur getActiveTokensCount: ' . $e->getMessage());
            return 0;
        }
    }

    // ========== PROCESSUS COMPLET DE RÉINITIALISATION ==========

    /**
     * Processus complet de demande de réinitialisation
     */
    public function requestPasswordReset(string $email): bool
    {
        try {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if (!$user) {
                // Ne pas révéler si l'email existe ou pas (sécurité)
                $this->logger->info('📧 Demande de reset pour email non trouvé: ' . $email);
                return true;
            }

            // Créer un token
            $token = $this->createToken($user);

            // Envoyer l'email
            $this->emailService->sendPasswordResetEmail($user, $token->getToken());

            $this->logger->info('📧 Email de réinitialisation envoyé à: ' . $email);
            return true;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur requestPasswordReset: ' . $e->getMessage());
            return false;
        }
    }

    // ========== GETTERS/SETTERS ==========

    public function getCurrentToken(): ?string
    {
        return $this->currentToken;
    }

    public function setCurrentToken(?string $token): self
    {
        $this->currentToken = $token;
        return $this;
    }

    public function getCurrentUserId(): ?int
    {
        return $this->currentUserId;
    }

    public function setCurrentUserId(?int $userId): self
    {
        $this->currentUserId = $userId;
        return $this;
    }
}