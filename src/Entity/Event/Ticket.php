<?php
// src/Entity/Event/Ticket.php

namespace App\Entity\Event;

use App\Entity\User\UserModel;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: "event_ticket")]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "ticket_code", length: 50, unique: true)]
    #[Assert\NotBlank(message: "Le code du ticket est requis")]
    private ?string $ticketCode = null;

    #[ORM\Column(name: "qr_code", type: Types::TEXT, nullable: true)]
    private ?string $qrCode = null;

    #[ORM\Column(name: "is_used", type: Types::BOOLEAN, options: ["default" => false])]
    private ?bool $isUsed = false;

    #[ORM\Column(name: "used_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $usedAt = null;

    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: "user_id", type: "integer", nullable: true)]
    private ?int $userId = null;

    // ==================== RELATIONS (NETTOYÉES) ====================

    // Relation vers l'événement (Propriétaire de la relation)
    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: "tickets")]
    #[ORM\JoinColumn(name: "event_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?Event $event = null;

    // Relation vers l'utilisateur
    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "Id_User", nullable: false)]
    private ?UserModel $user = null;

    public function __construct()
    {
        $this->isUsed = false;
        $this->createdAt = new \DateTime();
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int { return $this->id; }

    public function getTicketCode(): ?string { return $this->ticketCode; }
    public function setTicketCode(string $ticketCode): self { $this->ticketCode = $ticketCode; return $this; }

    public function getQrCode(): ?string { return $this->qrCode; }
    public function setQrCode(?string $qrCode): self { $this->qrCode = $qrCode; return $this; }

    public function isUsed(): ?bool { return $this->isUsed; }
    public function setIsUsed(bool $isUsed): self { $this->isUsed = $isUsed; return $this; }

    public function getUsedAt(): ?\DateTimeInterface { return $this->usedAt; }
    public function setUsedAt(?\DateTimeInterface $usedAt): self { $this->usedAt = $usedAt; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $event): self { $this->event = $event; return $this; }

    public function getUser(): ?UserModel { return $this->user; }
    public function setUser(?UserModel $user): self { $this->user = $user; return $this; }

    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(?int $userId): self { $this->userId = $userId; return $this; }

    // ==================== MÉTHODES UTILITAIRES ====================

    public static function generateTicketCode(int $eventId, int $userId): string
    {
        $timestamp = substr((string) time(), -7);
        return sprintf('EVT-%d-%d-%s', $eventId, $userId, $timestamp);
    }

    public function isValid(): bool { return !$this->isUsed; }

    public function markAsUsed(): self
    {
        $this->isUsed = true;
        $this->usedAt = new \DateTime();
        return $this;
    }

    public function __toString(): string
    {
        return $this->ticketCode ?? 'Ticket #' . $this->id;
    }
}