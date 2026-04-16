<?php
namespace App\Entity\Resource;

use App\Repository\Resource\ReservationResourceRepository;
use App\Entity\Event\Event;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationResourceRepository::class)]
#[ORM\Table(name: "reservation_resource")]
class ReservationResource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "resource_type", type: "string", columnDefinition: "ENUM('SALLE', 'EQUIPEMENT')")]
    #[Assert\NotBlank(message: "Le type de ressource est obligatoire")]
    #[Assert\Choice(choices: ["SALLE", "EQUIPEMENT"], message: "Le type de ressource doit être 'SALLE' ou 'EQUIPEMENT'")]
    private ?string $resourceType = null;

    #[ORM\ManyToOne(targetEntity: Salle::class)]
    #[ORM\JoinColumn(name: "salle_id", referencedColumnName: "id", onDelete: "SET NULL")]
    private ?Salle $salle = null;

    #[ORM\ManyToOne(targetEntity: Equipement::class)]
    #[ORM\JoinColumn(name: "equipement_id", referencedColumnName: "id", onDelete: "SET NULL")]
    private ?Equipement $equipement = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(name: "event_id", referencedColumnName: "id", onDelete: "CASCADE")]
    #[Assert\NotBlank(message: "L'événement est obligatoire")]
    private ?Event $event = null;

    #[ORM\Column(name: "reservation_date_start_time", type: "date")]
    #[Assert\NotBlank(message: "La date de début est obligatoire")]
    #[Assert\Type("\DateTimeInterface", message: "La date de début doit être une date valide")]
    #[Assert\GreaterThanOrEqual("today", message: "La date de début ne peut pas être dans le passé")]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(name: "end_time", type: "date")]
    #[Assert\NotBlank(message: "La date de fin est obligatoire")]
    #[Assert\Type("\DateTimeInterface", message: "La date de fin doit être une date valide")]
    #[Assert\Expression("this.getStartTime() < this.getEndTime()", message: "La date de fin doit être postérieure à la date de début")]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: "integer", options: ["default" => 1])]
    #[Assert\NotBlank(message: "La quantité est obligatoire")]
    #[Assert\Type(type: "integer", message: "La quantité doit être un nombre entier")]
    #[Assert\Positive(message: "La quantité doit être supérieure à 0")]
    #[Assert\Range(min: 1, max: 100, notInRangeMessage: "La quantité doit être entre {{ min }} et {{ max }}")]
    private ?int $quantity = 1;

    // Propriété pour calculer la quantité restante
    private ?int $remainingQuantity = null;

    public function getRemainingQuantity(): ?int
    {
        return $this->remainingQuantity;
    }

    public function setRemainingQuantity(int $remainingQuantity): self
    {
        $this->remainingQuantity = $remainingQuantity;
        return $this;
    }

    // --- GETTERS & SETTERS ---
    public function getId(): ?int { return $this->id; }

    public function getResourceType(): ?string { return $this->resourceType; }
    public function setResourceType(string $resourceType): self { $this->resourceType = $resourceType; return $this; }

    public function getSalle(): ?Salle { return $this->salle; }
    public function setSalle(?Salle $salle): self { $this->salle = $salle; return $this; }

    public function getEquipement(): ?Equipement { return $this->equipement; }
    public function setEquipement(?Equipement $equipement): self { $this->equipement = $equipement; return $this; }

    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $event): self { $this->event = $event; return $this; }

    public function getStartTime(): ?\DateTimeInterface { return $this->startTime; }
    public function setStartTime(\DateTimeInterface $startTime): self { $this->startTime = $startTime; return $this; }

    public function getEndTime(): ?\DateTimeInterface { return $this->endTime; }
    public function setEndTime(\DateTimeInterface $endTime): self { $this->endTime = $endTime; return $this; }

    public function getQuantity(): ?int { return $this->quantity; }
    public function setQuantity(?int $quantity): self { $this->quantity = $quantity; return $this; }
    // Dans src/Entity/Resource/ReservationResource.php

public function __construct()
{
    $this->startTime = new \DateTime();
    $this->endTime = new \DateTime('+1 day');
}
}