<?php
// src/Entity/Category.php

namespace App\Entity\Event;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity]
#[ORM\Table(name: "event_category")]
class Category
{
    // ==================== ATTRIBUTS ====================

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom de la catégorie est requis")]
    #[Assert\Length(
        max: 100,
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(name: "is_active", type: Types::BOOLEAN, options: ["default" => true])]
    private ?bool $isActive = true;

    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: "updated_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // ==================== RELATIONS ====================

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Event::class)]
    private Collection $events;

    // ==================== CONSTRUCTEUR ====================

    public function __construct()
    {
        $this->isActive = true;
        $this->events = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ==================== RELATIONS ====================

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setCategory($this);
        }
        return $this;
    }

    public function removeEvent(Event $event): self
    {
        if ($this->events->removeElement($event)) {
            if ($event->getCategory() === $this) {
                $event->setCategory(null);
            }
        }
        return $this;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Retourne le nombre d'événements dans cette catégorie
     */
    public function getEventCount(): int
    {
        return $this->events->count();
    }

    /**
     * Retourne le statut sous forme de badge texte
     */
    public function getStatusBadge(): string
    {
        return $this->isActive ? "✅ Active" : "❌ Inactive";
    }

    /**
     * Retourne l'icône avec le nom (pour affichage)
     */
    public function getDisplayName(): string
    {
        return ($this->icon ? $this->icon . " " : "") . $this->name;
    }

    /**
     * Vérifie si la catégorie est valide
     */
    public function isValid(): bool
    {
        return !empty($this->name) && strlen($this->name) <= 100;
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}