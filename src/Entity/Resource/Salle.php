<?php

namespace App\Entity\Resource;

use App\Repository\Resource\SalleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SalleRepository::class)]
class Salle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom de la salle est obligatoire")]
    #[Assert\Length(min: 2, max: 255, minMessage: "Le nom doit contenir au moins {{ limit }} caractères", maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères")]
    #[Assert\Type(type: "string", message: "Le nom doit être une chaîne de caractères")]
    private ?string $name = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "La capacité est obligatoire")]
    #[Assert\Positive(message: "La capacité doit être un nombre positif")]
    #[Assert\Type(type: "integer", message: "La capacité doit être un nombre entier")]
    #[Assert\Range(min: 1, max: 1000, notInRangeMessage: "La capacité doit être entre {{ min }} et {{ max }} places")]
    private ?int $capacity = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le bâtiment est obligatoire")]
    #[Assert\Length(min: 2, max: 50, minMessage: "Le nom du bâtiment doit contenir au moins {{ limit }} caractères", maxMessage: "Le nom du bâtiment ne peut pas dépasser {{ limit }} caractères")]
    #[Assert\Type(type: "string", message: "Le bâtiment doit être une chaîne de caractères")]
    private ?string $building = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "L'étage est obligatoire")]
    #[Assert\Positive(message: "L'étage doit être un nombre positif")]
    #[Assert\Type(type: "integer", message: "L'étage doit être un nombre entier")]
    #[Assert\Range(min: 0, max: 50, notInRangeMessage: "L'étage doit être entre {{ min }} et {{ max }}")]
    private ?int $floor = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le statut est obligatoire")]
    #[Assert\Choice(choices: ["DISPONIBLE", "INDISPONIBLE", "MAINTENANCE"], message: "Le statut doit être 'DISPONIBLE', 'INDISPONIBLE' ou 'MAINTENANCE'")]
    #[Assert\Type(type: "string", message: "Le statut doit être une chaîne de caractères")]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

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

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getBuilding(): ?string
    {
        return $this->building;
    }

    public function setBuilding(string $building): static
    {
        $this->building = $building;

        return $this;
    }

    public function getFloor(): ?int
    {
        return $this->floor;
    }

    public function setFloor(int $floor): static
    {
        $this->floor = $floor;

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

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }
}
