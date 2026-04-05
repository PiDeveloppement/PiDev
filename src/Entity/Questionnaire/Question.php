<?php
// src/Entity/Question.php

namespace App\Entity\Questionnaire;


use App\Entity\Event\Event;
use App\Entity\Questionnaire\Feedback;
use App\Entity\User\UserModel;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity] 
#[ORM\Table(name: "questions")]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: "id_question", type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "id_event", type: "integer", nullable: true)]
    private ?int $eventId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "Le texte de la question est requis")]
    private ?string $texte = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reponse = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ["default" => 0])]
    #[Assert\PositiveOrZero(message: "Les points doivent être positifs")]
    private ?int $points = 0;

    #[ORM\Column(name: "id_user", type: "integer", nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $option1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $option2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $option3 = null;

    // Relations
    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(name: "id_event", referencedColumnName: "id", nullable: true)]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: UserModel::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(name: "id_user", referencedColumnName: "Id_User", nullable: true)]
    private ?UserModel $user = null;

    /**
     * @var Collection<int, Feedback>
     */
    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Feedback::class)]
    private Collection $feedbacks;

    public function __construct()
    {
        $this->feedbacks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventId(): ?int
    {
        return $this->eventId;
    }

    public function setEventId(?int $eventId): self
    {
        $this->eventId = $eventId;
        return $this;
    }

    public function getTexte(): ?string
    {
        return $this->texte;
    }

    public function setTexte(?string $texte): self
    {
        $this->texte = $texte;
        return $this;
    }

    public function getReponse(): ?string
    {
        return $this->reponse;
    }

    public function setReponse(?string $reponse): self
    {
        $this->reponse = $reponse;
        return $this;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(?int $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getOption1(): ?string
    {
        return $this->option1;
    }

    public function setOption1(?string $option1): self
    {
        $this->option1 = $option1;
        return $this;
    }

    public function getOption2(): ?string
    {
        return $this->option2;
    }

    public function setOption2(?string $option2): self
    {
        $this->option2 = $option2;
        return $this;
    }

    public function getOption3(): ?string
    {
        return $this->option3;
    }

    public function setOption3(?string $option3): self
    {
        $this->option3 = $option3;
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
            $feedback->setQuestion($this);
        }
        return $this;
    }

    public function removeFeedback(Feedback $feedback): self
    {
        if ($this->feedbacks->removeElement($feedback)) {
            if ($feedback->getQuestion() === $this) {
                $feedback->setQuestion(null);
            }
        }
        return $this;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Retourne toutes les options sous forme de tableau
     */
    public function getOptions(): array
    {
        $options = [];
        if ($this->reponse !== null) {
            $options[] = $this->reponse;
        }
        if ($this->option1 !== null) {
            $options[] = $this->option1;
        }
        if ($this->option2 !== null) {
            $options[] = $this->option2;
        }
        if ($this->option3 !== null) {
            $options[] = $this->option3;
        }
        return $options;
    }

    /**
     * Retourne le nom de l'événement associé
     */
    public function getNomEvent(): ?string
    {
        return $this->event?->getTitle();
    }

    /**
     * Alias pour compatibilité avec le code JavaFX
     */
    public function getIdQuestion(): ?int
    {
        return $this->id;
    }

    /**
     * Alias pour compatibilité avec le code JavaFX
     */
    public function getIdEvent(): ?int
    {
        return $this->eventId;
    }

    public function __toString(): string
    {
        return $this->texte ?? 'Question #' . $this->id;
    }
    
}