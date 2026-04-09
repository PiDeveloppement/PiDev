<?php

namespace App\Entity\Event;

use App\Entity\User\UserModel;
use App\Entity\Questionnaire\Question;
use App\Repository\Event\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: "event")]
class Event
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est requis")]
    #[Assert\Length(min: 5, max: 100)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description est requise")]
    #[Assert\Length(min: 10, max: 1000)]
    private ?string $description = null;

    #[ORM\Column(name: "start_date", type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(name: "end_date", type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(length: 255)]
    private ?string $location = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gouvernorat = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $capacity = 50;

    #[ORM\Column(name: "image_url", length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(name: "category_id", type: "integer", nullable: true)]
    private ?int $categoryId = null;

    #[ORM\Column(name: "created_by", type: "integer", nullable: true)]
    private ?int $createdBy = null;

    #[ORM\Column(length: 20, options: ["default" => "DRAFT"])]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: "is_free", type: Types::BOOLEAN, options: ["default" => true])]
    private ?bool $isFree = true;

    #[ORM\Column(name: "ticket_price", type: Types::FLOAT, options: ["default" => 0])]
    private ?float $ticketPrice = 0.0;

    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: "updated_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'events')]
    #[ORM\JoinColumn(name: "category_id", referencedColumnName: "id", nullable: true)]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: "created_by", referencedColumnName: "Id_User", nullable: true)]
    private ?UserModel $creator = null;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Ticket::class)]
    private Collection $tickets;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Question::class)]
    private Collection $questions;

    public function __construct()
    {
        $this->status = self::STATUS_DRAFT;
        $this->isFree = true;
        $this->ticketPrice = 0.0;
        $this->capacity = 50;
        $this->createdAt = new \DateTime();
        $this->tickets = new ArrayCollection();
        $this->questions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function setStartDate(\DateTimeInterface $startDate): self { $this->startDate = $startDate; return $this; }

    public function getEndDate(): ?\DateTimeInterface { return $this->endDate; }
    public function setEndDate(\DateTimeInterface $endDate): self { $this->endDate = $endDate; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(string $location): self { $this->location = $location; return $this; }

    public function getGouvernorat(): ?string { return $this->gouvernorat; }
    public function setGouvernorat(?string $gouvernorat): self { $this->gouvernorat = $gouvernorat; return $this; }

    public function getVille(): ?string { return $this->ville; }
    public function setVille(?string $ville): self { $this->ville = $ville; return $this; }

    public function getCapacity(): ?int { return $this->capacity; }
    public function setCapacity(int $capacity): self { $this->capacity = $capacity; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): self { $this->imageUrl = $imageUrl; return $this; }

    public function getCategoryId(): ?int { return $this->categoryId; }
    public function setCategoryId(?int $categoryId): self { $this->categoryId = $categoryId; return $this; }

    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function setCreatedBy(?int $createdBy): self { $this->createdBy = $createdBy; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self
    {
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED])) {
            $status = self::STATUS_DRAFT;
        }
        $this->status = $status;
        return $this;
    }

    public function isFree(): ?bool { return $this->isFree; }
    public function isIsFree(): ?bool { return $this->isFree; }
    public function setIsFree(bool $isFree): self { $this->isFree = $isFree; return $this; }

    public function getTicketPrice(): ?float { return $this->ticketPrice; }
    public function setTicketPrice(float $ticketPrice): self { $this->ticketPrice = $ticketPrice; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        if ($category) $this->categoryId = $category->getId();
        return $this;
    }

    public function getCreator(): ?UserModel { return $this->creator; }
    public function setCreator(?UserModel $creator): self
    {
        $this->creator = $creator;
        if ($creator) $this->createdBy = $creator->getId();
        return $this;
    }

    public function getTickets(): Collection { return $this->tickets; }
    public function addTicket(Ticket $ticket): self
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setEvent($this);
        }
        return $this;
    }
    public function removeTicket(Ticket $ticket): self
    {
        if ($this->tickets->removeElement($ticket)) {
            if ($ticket->getEvent() === $this) $ticket->setEvent(null);
        }
        return $this;
    }

    public function getQuestions(): Collection { return $this->questions; }
    public function addQuestion(Question $question): self
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setEvent($this);
        }
        return $this;
    }
    public function removeQuestion(Question $question): self
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getEvent() === $this) $question->setEvent(null);
        }
        return $this;
    }

    public function isValid(): bool
    {
        return !empty($this->title) &&
            $this->startDate !== null &&
            $this->endDate !== null &&
            $this->startDate < $this->endDate;
    }

    public function isFull(): bool { return $this->getParticipantsCount() >= $this->capacity; }
    public function getParticipantsCount(): int { return $this->tickets->count(); }

    public function getDurationInHours(): float
    {
        if (!$this->startDate || !$this->endDate) return 0;
        $interval = $this->startDate->diff($this->endDate);
        return $interval->h + ($interval->days * 24);
    }

    public function getStatusDisplay(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_PUBLISHED => 'Publié',
            default => 'Inconnu',
        };
    }

    public function getPriceDisplay(): string
    {
        return $this->isFree ? 'Gratuit' : sprintf('%.2f DT', $this->ticketPrice);
    }

    public function getFormattedStartDate(): string
    {
        return $this->startDate ? $this->startDate->format('d/m/Y H:i') : '';
    }

    public function getFormattedEndDate(): string
    {
        return $this->endDate ? $this->endDate->format('d/m/Y H:i') : '';
    }

    public function getFormattedCreatedAt(): string
    {
        return $this->createdAt ? $this->createdAt->format('d/m/Y H:i') : '';
    }

    public function getTemporalStatus(): string
    {
        $now = new \DateTime();
        if ($this->endDate && $this->endDate < $now) return 'Terminé';
        if ($this->startDate && $this->startDate > $now) return 'À venir';
        if ($this->startDate && $this->endDate && $this->startDate <= $now && $this->endDate >= $now) return 'En cours';
        return 'Inconnu';
    }

    public function getDisplayName(): string
    {
        return sprintf('%s (%s)', $this->title, $this->getStatusDisplay());
    }

    public function __toString(): string
    {
        return $this->title ?? 'Événement #' . $this->id;
    }
}