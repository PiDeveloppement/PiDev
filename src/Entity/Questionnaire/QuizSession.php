<?php

namespace App\Entity\Questionnaire;

use App\Entity\User\UserModel;
use App\Entity\Event\Event;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: "quiz_sessions")]
class QuizSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id")]
    private ?int $id;

    #[ORM\Column(name: "session_token", type: "string", length: 255, unique: true)]
    private ?string $sessionToken = null;

    #[ORM\Column(name: "recaptcha_verified", type: "boolean", options: ["default" => false])]
    private bool $recaptchaVerified = false;

    #[ORM\Column(name: "recaptcha_token", type: "string", length: 512, nullable: true)]
    private ?string $recaptchaToken = null;

    #[ORM\Column(name: "started_at", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(name: "completed_at", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(name: "status", type: "string", length: 20, options: ["default" => "pending"])]
    #[Assert\Choice(choices: ["pending", "started", "completed", "aborted"], message: "Statut invalide")]
    private string $status = "pending";

    #[ORM\Column(name: "ip_address", type: "string", length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: "user_agent", type: "text", nullable: true)]
    private ?string $userAgent = null;

    // Relations
    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "Id_User", nullable: true, onDelete: "CASCADE")]
    private ?UserModel $user = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(name: "event_id", referencedColumnName: "id", nullable: true, onDelete: "CASCADE")]
    private ?Event $event = null;

    public function __construct()
    {
        $this->sessionToken = bin2hex(random_bytes(32));
    }

    // Getters & Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionToken(): ?string
    {
        return $this->sessionToken;
    }

    public function setSessionToken(string $sessionToken): self
    {
        $this->sessionToken = $sessionToken;
        return $this;
    }

    public function isRecaptchaVerified(): bool
    {
        return $this->recaptchaVerified;
    }

    public function setRecaptchaVerified(bool $recaptchaVerified): self
    {
        $this->recaptchaVerified = $recaptchaVerified;
        return $this;
    }

    public function getRecaptchaToken(): ?string
    {
        return $this->recaptchaToken;
    }

    public function setRecaptchaToken(?string $recaptchaToken): self
    {
        $this->recaptchaToken = $recaptchaToken;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeInterface $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getUser(): ?UserModel
    {
        return $this->user;
    }

    public function setUser(?UserModel $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;
        return $this;
    }

    // Méthodes utilitaires
    public function canStart(): bool
    {
        return $this->recaptchaVerified && $this->status === "pending";
    }

    public function startQuiz(): self
    {
        if ($this->canStart()) {
            $this->status = "started";
            $this->startedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function completeQuiz(): self
    {
        $this->status = "completed";
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isExpired(): bool
    {
        if ($this->startedAt === null) {
            return false;
        }
        
        $expiration = (new \DateTime($this->startedAt->format('Y-m-d H:i:s')))->modify('+2 hours');
        return new \DateTime() > $expiration;
    }
}