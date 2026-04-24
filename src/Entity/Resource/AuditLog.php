<?php

namespace App\Entity\Resource;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User\UserModel;
use App\Entity\Resource\ReservationResource;
use App\Entity\Resource\Salle;
use App\Entity\Resource\Equipement;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Resource\AuditLogRepository")
 * @ORM\Table(name="historique_logs")
 */
#[ORM\HasLifecycleCallbacks]
class AuditLog
{
    public const ACTION_CREATE = 'CREATE';
    public const ACTION_UPDATE = 'UPDATE';
    public const ACTION_DELETE = 'DELETE';
    
    public const RESOURCE_RESERVATION = 'RESERVATION';
    public const RESOURCE_SALLE = 'SALLE';
    public const RESOURCE_EQUIPEMENT = 'EQUIPEMENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 20)]
    private ?string $action = null;

    #[ORM\Column(type: "string", length: 50)]
    private ?string $resourceType = null;

    #[ORM\Column(type: "integer")]
    private ?int $resourceId = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $resourceName = null;

    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", onDelete: "SET NULL")]
    private ?UserModel $user = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $oldValues = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $newValues = null;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }

    public function setResourceType(string $resourceType): self
    {
        $this->resourceType = $resourceType;
        return $this;
    }

    public function getResourceId(): ?int
    {
        return $this->resourceId;
    }

    public function setResourceId(int $resourceId): self
    {
        $this->resourceId = $resourceId;
        return $this;
    }

    public function getResourceName(): ?string
    {
        return $this->resourceName;
    }

    public function setResourceName(string $resourceName): self
    {
        $this->resourceName = $resourceName;
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

    public function getOldValues(): ?string
    {
        return $this->oldValues;
    }

    public function setOldValues(?string $oldValues): self
    {
        $this->oldValues = $oldValues;
        return $this;
    }

    public function getNewValues(): ?string
    {
        return $this->newValues;
    }

    public function setNewValues(?string $newValues): self
    {
        $this->newValues = $newValues;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
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

    public function getActionLabel(): string
    {
        return match($this->action) {
            self::ACTION_CREATE => 'Créé',
            self::ACTION_UPDATE => 'Modifié',
            self::ACTION_DELETE => 'Supprimé',
            default => $this->action
        };
    }

    public function getResourceTypeLabel(): string
    {
        return match($this->resourceType) {
            self::RESOURCE_RESERVATION => 'Réservation',
            self::RESOURCE_SALLE => 'Salle',
            self::RESOURCE_EQUIPEMENT => 'Équipement',
            default => $this->resourceType
        };
    }

    public function getDescription(): string
    {
        $userName = $this->user ? $this->user->getFullName() : 'Utilisateur inconnu';
        $time = $this->createdAt->format('H:i');
        
        return sprintf(
            "%s %s par %s à %s",
            $this->getResourceName(),
            $this->getActionLabel(),
            $userName,
            $time
        );
    }
}