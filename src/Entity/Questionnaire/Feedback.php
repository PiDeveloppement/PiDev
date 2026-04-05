<?php

namespace App\Entity\Questionnaire;

use App\Entity\User\UserModel;
use App\Entity\Questionnaire\Question;
use App\Entity\Event\Event; // Import important
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: "feedbacks")]
class Feedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: "id_feedback", type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "reponse_donnee", length: 255, nullable: true)]
    private ?string $reponseDonnee = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comments = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ["default" => 0])]
    #[Assert\Range(min: 0, max: 5)]
    private ?int $etoiles = 0;

    // ==================== RELATIONS CORRIGÉES ====================

    // Relation vers l'événement (pour que Event#feedbacks fonctionne)
    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: "feedbacks")]
    #[ORM\JoinColumn(name: "id_event", referencedColumnName: "id", nullable: true)]
    private ?Event $event = null;

    // Relation vers l'utilisateur
    #[ORM\ManyToOne(targetEntity: UserModel::class, inversedBy: "feedbacks")]
    #[ORM\JoinColumn(name: "id_user", referencedColumnName: "Id_User", nullable: true)]
    private ?UserModel $user = null;

    // Relation vers la question
    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: "feedbacks")]
    #[ORM\JoinColumn(name: "id_question", referencedColumnName: "id_question", nullable: true)]
    private ?Question $question = null;

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int { return $this->id; }

    public function getReponseDonnee(): ?string { return $this->reponseDonnee; }
    public function setReponseDonnee(?string $reponseDonnee): self { $this->reponseDonnee = $reponseDonnee; return $this; }

    public function getComments(): ?string { return $this->comments; }
    public function setComments(?string $comments): self { $this->comments = $comments; return $this; }

    public function getEtoiles(): ?int { return $this->etoiles; }
    public function setEtoiles(?int $etoiles): self { $this->etoiles = $etoiles; return $this; }

    public function getUser(): ?UserModel { return $this->user; }
    public function setUser(?UserModel $user): self { $this->user = $user; return $this; }

    public function getQuestion(): ?Question { return $this->question; }
    public function setQuestion(?Question $question): self { $this->question = $question; return $this; }

    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $event): self { $this->event = $event; return $this; }

    // ==================== MÉTHODES DE COMPATIBILITÉ ====================

    public function getUserId(): ?int { return $this->user?->getId(); }
    public function getQuestionId(): ?int { return $this->question?->getId(); }

    // ==================== MÉTHODES UTILITAIRES ====================

    public function getUserName(): string 
    {
        return $this->user ? $this->user->getFullName() : 'Utilisateur inconnu';
    }

    public function getEventName(): string
    {
        return $this->event ? $this->event->getTitle() : 'Événement inconnu';
    }

    public function getStarsDisplay(): string
    {
        return str_repeat('★', $this->etoiles ?? 0) . str_repeat('☆', 5 - ($this->etoiles ?? 0));
    }

    public function __toString(): string
    {
        return sprintf('Feedback #%d - %d étoiles', $this->id ?? 0, $this->etoiles ?? 0);
    }
}