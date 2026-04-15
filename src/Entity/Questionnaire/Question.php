<?php

namespace App\Entity\Questionnaire;
use App\Entity\Event\Event;
use App\Entity\User\UserModel;
use App\Repository\Questionnaire\QuestionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: "questions")] // Nom de ta table SQL
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_question")] // Correspond à ta PK dans le SQL
    private ?int $id = null;

    #[ORM\Column(name: "texte", type: "string", length: 255, nullable: true)]
    #[Assert\NotBlank(message: "L'énoncé de la question est obligatoire.")]
    #[Assert\Length(min: 5, max: 255, minMessage: "L'énoncé doit contenir au moins {{ limit }} caractères.", maxMessage: "L'énoncé ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $texte = null;

    #[ORM\Column(name: "reponse", type: "string", length: 255, nullable: true)]
    #[Assert\NotBlank(message: "La réponse correcte est obligatoire.")]
    #[Assert\Length(min: 2, max: 255, minMessage: "La réponse doit contenir au moins {{ limit }} caractères.", maxMessage: "La réponse ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $reponse = null;

    #[ORM\Column(name: "points", type: "integer", options: ["default" => 0])]
    #[Assert\Positive(message: "Le nombre de points doit être un nombre positif.")]
    #[Assert\Type(type: "integer", message: "Le nombre de points doit être un entier.")]
    private ?int $points = 0;

    #[ORM\Column(name: "option1", type: "string", length: 255, nullable: true)]
    private ?string $option1 = null;

    #[ORM\Column(name: "option2", type: "string", length: 255, nullable: true)]
    private ?string $option2 = null;

    #[ORM\Column(name: "option3", type: "string", length: 255, nullable: true)]
    private ?string $option3 = null;

    // Relation avec Event (id_event dans ton SQL)
    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: "questions")]
    #[ORM\JoinColumn(name: "id_event", referencedColumnName: "id", onDelete: "CASCADE")]
    private ?Event $event = null;

    // Relation avec User (id_user dans ton SQL)
    #[ORM\ManyToOne(targetEntity: UserModel::class, inversedBy: "questions")]
    #[ORM\JoinColumn(name: "id_user", referencedColumnName: "Id_User", nullable: true, onDelete: "CASCADE")]
    private ?UserModel $user = null;

    // --- GETTERS ET SETTERS ---

    public function getId(): ?int { return $this->id; }

    public function getTexte(): ?string { return $this->texte; }
    public function setTexte(?string $texte): self { $this->texte = $texte; return $this; }

    public function getReponse(): ?string { return $this->reponse; }
    public function setReponse(?string $reponse): self { $this->reponse = $reponse; return $this; }

    public function getPoints(): ?int { return $this->points; }
    public function setPoints(int $points): self { $this->points = $points; return $this; }

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
}