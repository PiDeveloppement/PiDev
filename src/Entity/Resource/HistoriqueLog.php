<?php

namespace App\Entity\Resource;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User\UserModel;
use App\Repository\Resource\HistoriqueLogRepository;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: HistoriqueLogRepository::class)]
#[ORM\Table(name: "historique_logs")]
class HistoriqueLog
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
    private ?int $id;

    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "Id_User", onDelete: "SET NULL")]
    private ?UserModel $user = null;

    #[ORM\Column(length: 20)]
    private ?string $action = null;

    #[ORM\Column(length: 50)]
    private ?string $resourceType = null;

    #[ORM\Column(type: "integer")]
    private ?int $resourceId = null;

    #[ORM\Column(length: 255)]
    private ?string $resourceName = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $oldValues = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $newValues = null;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function getId(): ?int
    {
        return $this->id;
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
            self::ACTION_CREATE => 'Création',
            self::ACTION_UPDATE => 'Modification',
            self::ACTION_DELETE => 'Suppression',
            default => $this->action ?? 'Action inconnue'
        };
    }

    public function getResourceTypeLabel(): string
    {
        return match($this->resourceType) {
            self::RESOURCE_RESERVATION => 'Réservation',
            self::RESOURCE_SALLE => 'Salle',
            self::RESOURCE_EQUIPEMENT => 'Équipement',
            default => $this->resourceType ?? 'Ressource inconnue'
        };
    }

    public function getDescription(): string
    {
        $userName = $this->user ? $this->user->getFullName() : 'Utilisateur inconnu';
        $time = $this->createdAt?->format('H:i') ?? '00:00';
        
        return sprintf(
            "%s %s par %s à %s",
            $this->getResourceName(),
            $this->getActionLabel(),
            $userName,
            $time
        );
    }

    public function getChanges(): ?array
    {
        if ($this->oldValues || $this->newValues) {
            $old = $this->oldValues ? json_decode($this->oldValues, true) : [];
            $new = $this->newValues ? json_decode($this->newValues, true) : [];
            
            $changes = [];
            foreach ($old as $key => $oldValue) {
                if (isset($new[$key]) && $oldValue !== $new[$key]) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $new[$key]
                    ];
                }
            }
            
            return $changes;
        }
        
        return null;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }
}