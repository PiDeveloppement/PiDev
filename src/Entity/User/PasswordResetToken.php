<?php

namespace App\Entity\User;

use App\Repository\User\PasswordResetTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PasswordResetTokenRepository::class)]
#[ORM\Table(name: "password_reset_tokens")]
class PasswordResetToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "user_id", type: "integer")]
    private ?int $userId = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $token = null;

    #[ORM\Column(name: "expiry_date", type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ["default" => false])]
    private ?bool $used = false;

    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    // ==================== RELATIONS ====================

   #[ORM\OneToOne(inversedBy: 'resetToken')]  // ← Changé de ManyToOne à OneToOne
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "Id_User", nullable: false)]
    private ?UserModel $user = null;

    // ==================== CONSTRUCTEUR ====================

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Constructeur statique pour créer un token pour un utilisateur
     */
    public static function createForUser(UserModel $user): self
    {
        $token = new self();
        $token->setUser($user);
        $token->setUserId($user->getId());
        $token->setToken(Uuid::v4()->toRfc4122());
        $token->setExpiryDate(new \DateTime('+1 hour'));
        $token->setUsed(false);
        
        return $token;
    }

    /**
     * Constructeur statique avec ID utilisateur direct (compatible JavaFX)
     */
    public static function createForUserId(int $userId): self
    {
        $token = new self();
        $token->setUserId($userId);
        $token->setToken(Uuid::v4()->toRfc4122());
        $token->setExpiryDate(new \DateTime('+1 hour'));
        $token->setUsed(false);
        
        return $token;
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;
        return $this;
    }

    public function isUsed(): ?bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): self
    {
        $this->used = $used;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // ==================== RELATIONS ====================

    public function getUser(): ?UserModel
    {
        return $this->user;
    }

    public function setUser(?UserModel $user): self
    {
        $this->user = $user;
        if ($user) {
            $this->userId = $user->getId();
        }
        return $this;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifie si le token est valide (non utilisé et non expiré)
     */
    public function isValid(): bool
    {
        $now = new \DateTime();
        return !$this->used && $this->expiryDate > $now;
    }

    /**
     * Vérifie si le token est expiré
     */
    public function isExpired(): bool
    {
        $now = new \DateTime();
        return $this->expiryDate <= $now;
    }

    /**
     * Génère le lien de réinitialisation
     */
    public function generateResetLink(string $baseUrl = 'http://localhost:8000'): string
    {
        return $baseUrl . '/reset-password?token=' . $this->token;
    }

    /**
     * Génère le lien de réinitialisation pour l'API
     */
    public function generateApiResetLink(): string
    {
        return '/api/reset-password/' . $this->token;
    }

    /**
     * Retourne le temps restant avant expiration en minutes
     */
    public function getRemainingMinutes(): int
    {
        $now = new \DateTime();
        $interval = $now->diff($this->expiryDate);
        
        if ($this->isExpired()) {
            return 0;
        }
        
        return ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    }

    /**
     * Retourne le temps restant formaté
     */
    public function getRemainingTimeFormatted(): string
    {
        $minutes = $this->getRemainingMinutes();
        
        if ($minutes <= 0) {
            return 'Expiré';
        }
        
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $hours . ' heure' . ($hours > 1 ? 's' : '') . 
               ($remainingMinutes > 0 ? ' ' . $remainingMinutes . ' min' : '');
    }

    /**
     * Invalide le token (le marque comme utilisé)
     */
    public function invalidate(): self
    {
        $this->used = true;
        return $this;
    }

    /**
     * Prolonge la durée de validité
     */
    public function extendValidity(int $hours = 1): self
    {
        $this->expiryDate = (new \DateTime())->modify('+' . $hours . ' hours');
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'PasswordResetToken #%d - User: %d - Expires: %s - %s',
            $this->id ?? 0,
            $this->userId ?? 0,
            $this->expiryDate?->format('Y-m-d H:i:s') ?? 'N/A',
            $this->isValid() ? '✅ Valide' : '❌ Invalide'
        );
    }
}