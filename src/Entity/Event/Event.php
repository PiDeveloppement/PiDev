<?php

namespace App\Entity\Event;

use App\Entity\Event\Event_Category;
use App\Entity\Event\Event_Ticket;
use App\Entity\Questionnaire\Feedback;
use App\Entity\Questionnaire\Question;
use App\Entity\User\UserModel;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity]
#[ORM\Table(name: "event")]
class Event
{
    // ==================== CONSTANTES ====================

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';

    // ==================== ATTRIBUTS ====================

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est requis")]
    #[Assert\Length(
        min: 5,
        max: 100,
        minMessage: "Le titre doit faire au moins {{ limit }} caractères",
        maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description est requise")]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: "La description doit faire au moins {{ limit }} caractères",
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $description = null;

    #[ORM\Column(name: "start_date", type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: "La date de début est requise")]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(name: "end_date", type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: "La date de fin est requise")]
    #[Assert\GreaterThan(propertyPath: "startDate", message: "La date de fin doit être après la date de début")]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le lieu est requis")]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: "Le lieu doit faire au moins {{ limit }} caractères",
        maxMessage: "Le lieu ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $location = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gouvernorat = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive(message: "La capacité doit être d'au moins 1 place")]
    private ?int $capacity = 50;

    #[ORM\Column(name: "image_url", length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(name: "category_id", type: Types::INTEGER)]
    #[Assert\Positive(message: "La catégorie est requise")]
    private ?int $categoryId = null;

    #[ORM\Column(name: "created_by", type: Types::INTEGER)]
    private ?int $createdBy = null;

    #[ORM\Column(length: 20, options: ["default" => "DRAFT"])]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: "is_free", type: Types::BOOLEAN, options: ["default" => true])]
    private ?bool $isFree = true;

    #[ORM\Column(name: "ticket_price", type: Types::DECIMAL, precision: 10, scale: 2, options: ["default" => 0])]
    private ?string $ticketPrice = "0.00";

    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: "updated_at", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // ==================== RELATIONS ====================

    #[ORM\ManyToOne(targetEntity: Event_Category::class)]
    #[ORM\JoinColumn(name: "category_id", referencedColumnName: "id", nullable: true)]
    private ?Event_Category $category = null;

    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: "created_by", referencedColumnName: "Id_User", nullable: true)]
    private ?UserModel $creator = null;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Event_Ticket::class)]
    private Collection $tickets;

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Question::class)]
    private Collection $questions;

    /**
     * @var Collection<int, Feedback>
     */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Feedback::class)]
    private Collection $feedbacks;

    // ==================== CONSTRUCTEUR ====================

    public function __construct()
    {
        $this->status = self::STATUS_DRAFT;
        $this->isFree = true;
        $this->ticketPrice = "0.00";
        $this->capacity = 50;
        $this->createdAt = new \DateTime();
        $this->tickets = new ArrayCollection();
        $this->questions = new ArrayCollection();
        $this->feedbacks = new ArrayCollection();
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getGouvernorat(): ?string
    {
        return $this->gouvernorat;
    }

    public function setGouvernorat(?string $gouvernorat): self
    {
        $this->gouvernorat = $gouvernorat;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function setCategoryId(int $categoryId): self
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED])) {
            $status = self::STATUS_DRAFT;
        }
        $this->status = $status;
        return $this;
    }

    public function isFree(): ?bool
    {
        return $this->isFree;
    }

    public function setIsFree(bool $isFree): self
    {
        $this->isFree = $isFree;
        return $this;
    }

    public function getTicketPrice(): ?string
    {
        return $this->ticketPrice;
    }

    public function setTicketPrice(string $ticketPrice): self
    {
        $this->ticketPrice = $ticketPrice;
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

    public function getCategory(): ?Event_Category
    {
        return $this->category;
    }

    public function setCategory(?Event_Category $category): self
    {
        $this->category = $category;
        if ($category) {
            $this->categoryId = $category->getId();
        }
        return $this;
    }

    public function getCreator(): ?UserModel
    {
        return $this->creator;
    }

    public function setCreator(?UserModel $creator): self
    {
        $this->creator = $creator;
        if ($creator) {
            $this->createdBy = $creator->getId();
        }
        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Event_Ticket $ticket): self
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setEvent($this);
        }
        return $this;
    }

    public function removeTicket(Event_Ticket $ticket): self
    {
        if ($this->tickets->removeElement($ticket)) {
            if ($ticket->getEvent() === $this) {
                $ticket->setEvent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

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
            if ($question->getEvent() === $this) {
                $question->setEvent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Feedback>
     */
    public function getFeedbacks(): Collection
    {
        return $this->feedbacks;
    }

    public function addFeedback(Feedback $feedback): self
    {
        if (!$this->feedbacks->contains($feedback)) {
            $this->feedbacks->add($feedback);
           // $feedback->setEvent($this);
        }
        return $this;
    }

    public function removeFeedback(Feedback $feedback): self
    {
        if ($this->feedbacks->removeElement($feedback)) {
           // if ($feedback->getEvent() === $this) {
             //   $feedback->setEvent(null);
           // }
        }
        return $this;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifie si l'événement est valide
     */
    public function isValid(): bool
    {
        return !empty($this->title) &&
               $this->startDate !== null &&
               $this->endDate !== null &&
               $this->startDate < $this->endDate &&
               $this->categoryId > 0 &&
               $this->createdBy > 0;
    }

    /**
     * Vérifie si l'événement est complet
     */
    public function isFull(): bool
    {
        return $this->getParticipantsCount() >= $this->capacity;
    }

    /**
     * Retourne le nombre de participants
     */
    public function getParticipantsCount(): int
    {
        return $this->tickets->count();
    }

    /**
     * Retourne la durée en heures
     */
    public function getDurationInHours(): float
    {
        if (!$this->startDate || !$this->endDate) {
            return 0;
        }
        $interval = $this->startDate->diff($this->endDate);
        return $interval->h + ($interval->days * 24);
    }

    /**
     * Retourne le statut affichable
     */
    public function getStatusDisplay(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_PUBLISHED => 'Publié',
            default => 'Inconnu'
        };
    }

    /**
     * Retourne le type de prix
     */
    public function getPriceDisplay(): string
    {
        if ($this->isFree) {
            return 'Gratuit';
        }
        return sprintf('%.2f DT', (float)$this->ticketPrice);
    }

    /**
     * Retourne la date de début formatée
     */
    public function getFormattedStartDate(): string
    {
        if (!$this->startDate) {
            return '';
        }
        return $this->startDate->format('d/m/Y H:i');
    }

    /**
     * Retourne la date de fin formatée
     */
    public function getFormattedEndDate(): string
    {
        if (!$this->endDate) {
            return '';
        }
        return $this->endDate->format('d/m/Y H:i');
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
     * Retourne le statut temporel de l'événement
     */
    public function getTemporalStatus(): string
    {
        $now = new \DateTime();
        
        if ($this->endDate < $now) {
            return 'Terminé';
        }
        if ($this->startDate > $now) {
            return 'À venir';
        }
        if ($this->startDate <= $now && $this->endDate >= $now) {
            return 'En cours';
        }
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