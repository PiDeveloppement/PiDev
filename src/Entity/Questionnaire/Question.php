<?php
// src/Entity/Questionnaire/Question.php

namespace App\Entity\Questionnaire;

use App\Entity\Event\Event;
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

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "Le texte de la question est requis")]
    private ?string $texte = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reponse = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ["default" => 0])]
    #[Assert\PositiveOrZero(message: "Les points doivent être positifs")]
    private ?int $points = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $option1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $option2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $option3 = null;

    // ==================== RELATIONS CORRIGÉES ====================

    // Ajout de inversedBy pour correspondre à l'entité Event
    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: "questions")]
    #[ORM\JoinColumn(name: "id_event", referencedColumnName: "id", nullable: true)]
    private ?Event $event = null;

    // Suppression du $userId manuel, on utilise uniquement l'objet
    #[ORM\ManyToOne(targetEntity: UserModel::class, inversedBy: "questions")]
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

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int { return $this->id; }

    public function getTexte(): ?string { return $this->texte; }
    public function setTexte(?string $texte): self { $this->texte = $texte; return $this; }

    public function getReponse(): ?string { return $this->reponse; }
    public function setReponse(?string $reponse): self { $this->reponse = $reponse; return $this; }

    public function getPoints(): ?int { return $this->points; }
    public function setPoints(?int $points): self { $this->points = $points; return $this; }

    public function getOption1(): ?string { return $this->option1; }
    public function setOption1(?string $option1): self { $this->option1 = $option1; return $this; }

    public function getOption2(): ?string { return $this->option2; }
    public function setOption2(?string $option2): self { $this->option2 = $option2; return $this; }

    public function getOption3(): ?string { return $this->option3; }
    public function setOption3(?string $option3): self { $this->option3 = $option3; return $this; }

    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $event): self { $this->event = $event; return $this; }

    public function getUser(): ?UserModel { return $this->user; }
    public function setUser(?UserModel $user): self { $this->user = $user; return $this; }

    public function getFeedbacks(): Collection { return $this->feedbacks; }

    // ==================== MÉTHODES DE COMPATIBILITÉ (JAVA) ====================

    public function getEventId(): ?int { return $this->event?->getId(); }
    public function getUserId(): ?int { return $this->user?->getId(); }

    // ==================== MÉTHODES UTILITAIRES ====================

    public function getOptions(): array
    {
        return array_filter([$this->reponse, $this->option1, $this->option2, $this->option3]);
    }

    public function __toString(): string
    {
        return $this->texte ?? 'Question #' . $this->id;
    }
}