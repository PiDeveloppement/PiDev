<?php

namespace App\Entity\Resource;

use App\Repository\Resource\EquipementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EquipementRepository::class)]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom de l'équipement est obligatoire")]
    #[Assert\Length(min: 2, max: 255, minMessage: "Le nom doit contenir au moins {{ limit }} caractères", maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères")]
    #[Assert\Type(type: "string", message: "Le nom doit être une chaîne de caractères")]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le type d'équipement est obligatoire")]
    #[Assert\Length(min: 2, max: 255, minMessage: "Le type doit contenir au moins {{ limit }} caractères", maxMessage: "Le type ne peut pas dépasser {{ limit }} caractères")]
    #[Assert\Type(type: "string", message: "Le type doit être une chaîne de caractères")]
    private ?string $equipement_type = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le statut est obligatoire")]
    #[Assert\Choice(choices: ["DISPONIBLE", "INDISPONIBLE", "MAINTENANCE"], message: "Le statut doit être 'DISPONIBLE', 'INDISPONIBLE' ou 'MAINTENANCE'")]
    #[Assert\Type(type: "string", message: "Le statut doit être une chaîne de caractères")]
    private string $status = 'DISPONIBLE';

    #[ORM\Column]
    #[Assert\NotBlank(message: "La quantité est obligatoire")]
    #[Assert\Positive(message: "La quantité doit être un nombre positif")]
    #[Assert\Type(type: "integer", message: "La quantité doit être un nombre entier")]
    #[Assert\Range(min: 1, max: 1000, notInRangeMessage: "La quantité doit être entre {{ min }} et {{ max }}")]
    private int $quantity = 0;

    #[ORM\Column]
    private int $original_quantity = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image_path = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEquipementType(): ?string
    {
        return $this->equipement_type;
    }

    public function setEquipementType(string $equipement_type): static
    {
        $this->equipement_type = $equipement_type;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getOriginalQuantity(): int
    {
        return $this->original_quantity;
    }

    public function setOriginalQuantity(int $original_quantity): static
    {
        $this->original_quantity = $original_quantity;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->image_path;
    }

    public function setImagePath(?string $image_path): static
    {
        $this->image_path = $image_path;

        return $this;
    }
}
