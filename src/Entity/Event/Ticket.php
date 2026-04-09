<?php
// src/Entity/Ticket.php

namespace App\Entity\Event;

use App\Entity\User\UserModel;
use App\Repository\Event\TicketRepository;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: "event_ticket")]
class Ticket
{
    // ==================== ATTRIBUTS ====================

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "ticket_code", length: 50, unique: true)]
    #[Assert\NotBlank(message: "Le code du ticket est requis")]
    private ?string $ticketCode = null;

    #[ORM\Column(name: "event_id", type: Types::INTEGER)]
    #[Assert\Positive(message: "L'événement est requis")]
    private ?int $eventId = null;

    #[ORM\Column(name: "user_id", type: Types::INTEGER)]
    #[Assert\Positive(message: "L'utilisateur est requis")]
    private ?int $userId = null;

    #[ORM\Column(name: "qr_code", type: Types::TEXT, nullable: true)]
    private ?string $qrCode = null;

    #[ORM\Column(name: "is_used", type: Types::BOOLEAN, options: ["default" => false])]
    private ?bool $isUsed = false;

    #[ORM\Column(name: "used_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $usedAt = null;

    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    // ==================== RELATIONS ====================

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'tickets')]
    #[ORM\JoinColumn(name: "event_id", referencedColumnName: "id", nullable: true)]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "Id_User", nullable: true)]
    private ?UserModel $user = null;

    // ==================== CONSTRUCTEUR ====================

    public function __construct()
    {
        $this->isUsed = false;
        $this->createdAt = new \DateTime();
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicketCode(): ?string
    {
        return $this->ticketCode;
    }

    public function setTicketCode(string $ticketCode): self
    {
        $this->ticketCode = $ticketCode;
        return $this;
    }

    public function getEventId(): ?int
    {
        return $this->eventId;
    }

    public function setEventId(int $eventId): self
    {
        $this->eventId = $eventId;
        return $this;
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

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function setQrCode(?string $qrCode): self
    {
        $this->qrCode = $qrCode;
        return $this;
    }

    public function isUsed(): ?bool
    {
        return $this->isUsed;
    }

    public function setIsUsed(bool $isUsed): self
    {
        $this->isUsed = $isUsed;
        return $this;
    }

    public function getUsedAt(): ?\DateTimeInterface
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTimeInterface $usedAt): self
    {
        $this->usedAt = $usedAt;
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

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;
        if ($event) {
            $this->eventId = $event->getId();
        }
        return $this;
    }

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
     * Génère un code de ticket unique
     */
    public static function generateTicketCode(int $eventId, int $userId): string
    {
        $timestamp = substr((string) time(), -7);
        return sprintf('EVT-%d-%d-%s', $eventId, $userId, $timestamp);
    }

    /**
     * Vérifie si le ticket est valide (non utilisé)
     */
    public function isValid(): bool
    {
        return !$this->isUsed;
    }

    /**
     * Marque le ticket comme utilisé
     */
    public function markAsUsed(): self
    {
        $this->isUsed = true;
        $this->usedAt = new \DateTime();
        return $this;
    }

    /**
     * Retourne la date de création formatée
     */
    public function getFormattedCreatedAt(): string
    {
        if (!$this->createdAt) {
            return '';
        }
        return $this->createdAt->format('d/m/Y H:i');
    }

    /**
     * Retourne la date d'utilisation formatée
     */
    public function getFormattedUsedAt(): string
    {
        if (!$this->usedAt) {
            return 'Non utilisé';
        }
        return $this->usedAt->format('d/m/Y H:i');
    }

    public function __toString(): string
    {
        return $this->ticketCode ?? 'Ticket #' . $this->id;
    }
}
