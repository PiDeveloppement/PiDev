<?php

namespace App\Entity\Questionnaire;
use App\Entity\User\UserModel;
use App\Entity\Questionnaire\Question;


use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Service\Questionnaire\ContentModerationService;

#[ORM\Entity]
#[ORM\Table(name: "feedbacks")]
#[ORM\HasLifecycleCallbacks]
class Feedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: "id_feedback", type: "integer")]
    /** @phpstan-ignore property.onlyRead */
    private ?int $id;

    #[ORM\Column(name: "id_user", type: "integer", nullable: true, options: ["default" => 45])]
    private ?int $userId = 45;

    #[ORM\Column(name: "id_question", type: "integer", nullable: true)]
    private ?int $questionId = null;

    #[ORM\Column(name: "reponse_donnee", length: 255, nullable: true)]
    private ?string $reponseDonnee = null;

    #[ORM\Column(name: "comments", length: 255, nullable: true)]
    private ?string $comments = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ["default" => 0])]
    #[Assert\Range(
        min: 0,
        max: 5,
        notInRangeMessage: "La note doit être entre {{ min }} et {{ max }} étoiles"
    )]
    private ?int $etoiles = 0;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    // Relations avec les autres entités
    #[ORM\ManyToOne(targetEntity: UserModel::class)]
    #[ORM\JoinColumn(name: "id_user", referencedColumnName: "Id_User", nullable: true, onDelete: "CASCADE")]
    private ?UserModel $user = null;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(name: "id_question", referencedColumnName: "id_question", nullable: true, onDelete: "CASCADE")]
    private ?Question $question = null;

    private static ?ContentModerationService $moderationService = null;

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
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

    public function getQuestionId(): ?int
    {
        return $this->questionId;
    }

    public function setQuestionId(?int $questionId): self
    {
        $this->questionId = $questionId;
        return $this;
    }

    public function getReponseDonnee(): ?string
    {
        return $this->reponseDonnee;
    }

    public function setReponseDonnee(?string $reponseDonnee): self
    {
        $this->reponseDonnee = $reponseDonnee;
        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;
        return $this;
    }

    public function getEtoiles(): ?int
    {
        return $this->etoiles;
    }

    public function setEtoiles(?int $etoiles): self
    {
        $this->etoiles = $etoiles;
        return $this;
    }

    // ==================== MÉTHODES DE RELATION ====================

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

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): self
    {
        $this->question = $question;
        if ($question) {
            $this->questionId = $question->getId();
        }
        return $this;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Retourne le nom de l'utilisateur associé
     */
    public function getUserName(): string
    {
        if ($this->user) {
            return $this->user->getFullName();
        }
        return 'Utilisateur inconnu';
    }

    /**
     * Retourne le nom de l'événement associé via la question
     */
    public function getEventName(): string
    {
        if ($this->question && $this->question->getEvent()) {
            return $this->question->getEvent()->getTitle() ?? 'Quiz Général';
        }
        return 'Quiz Général';
    }

    public function getStarsDisplay(): string
    {
        return str_repeat('★', $this->etoiles ?? 0) . str_repeat('☆', 5 - ($this->etoiles ?? 0));
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

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function __toString(): string
    {
        return sprintf(
            'Feedback #%d - %d étoiles',
            $this->id ?? 0,
            $this->etoiles ?? 0
        );
    }

    // ==================== MODERATION SERVICE ====================

    public static function setModerationService(ContentModerationService $service): void
    {
        self::$moderationService = $service;
    }

    public static function getModerationService(): ?ContentModerationService
    {
        return self::$moderationService;
    }
}