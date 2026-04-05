<?php

namespace App\Entity\User;
use App\Entity\Questionnaire\Question;
use App\Entity\Questionnaire\Feedback;
use App\Entity\Role\Role;
use App\Repository\User\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: "user_model")]
class UserModel implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: "Id_User", type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "First_Name", length: 45)]
    #[Assert\NotBlank(message: "Le prénom est requis")]
    #[Assert\Length(min: 2, max: 45, minMessage: "Le prénom doit faire au moins 2 caractères")]
    private ?string $firstName = null;

    #[ORM\Column(name: "Last_Name", length: 45)]
    #[Assert\NotBlank(message: "Le nom est requis")]
    #[Assert\Length(min: 2, max: 45, minMessage: "Le nom doit faire au moins 2 caractères")]
    private ?string $lastName = null;

    #[ORM\Column(length: 45, unique: true)]
    #[Assert\NotBlank(message: "L'email est requis")]
    #[Assert\Email(message: "L'email '{{ value }}' n'est pas valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 45)]
    #[Assert\NotBlank(message: "La faculté est requise")]
    private ?string $faculte = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(name: "Role_Id", type: "integer")]
    private ?int $roleId ;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: "profile_picture_url", length: 500, nullable: true)]
    private ?string $profilePictureUrl = null;

    #[ORM\Column(name: "registration_date", type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $registrationDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinColumn(name: "Role_Id", referencedColumnName: "Id_Role", nullable: false)]
    private ?Role $role = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?PasswordResetToken $resetToken = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Feedback::class)]
    private Collection $feedbacks;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Question::class)]
    private Collection $questions;

    // Champ temporaire pour le formulaire (non persistant)
    private ?string $plainPassword = null;

    public function __construct()
    {
        $this->feedbacks = new ArrayCollection();
        $this->questions = new ArrayCollection();
        $this->registrationDate = new \DateTime();
    }

    // ==================== GETTERS & SETTERS DE BASE ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getFaculte(): ?string
    {
        return $this->faculte;
    }

    public function setFaculte(string $faculte): self
    {
        $this->faculte = $faculte;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getRoleId(): ?int
    {
        return $this->roleId;
    }

    public function setRoleId(int $roleId): self
    {
        $this->roleId = $roleId;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getProfilePictureUrl(): ?string
    {
        return $this->profilePictureUrl;
    }

    public function setProfilePictureUrl(?string $profilePictureUrl): self
    {
        $this->profilePictureUrl = $profilePictureUrl;
        return $this;
    }

    public function getRegistrationDate(): ?\DateTimeInterface
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(?\DateTimeInterface $registrationDate): self
    {
        $this->registrationDate = $registrationDate;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    // ==================== RELATIONS ====================

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getResetToken(): ?PasswordResetToken
    {
        return $this->resetToken;
    }

    public function setResetToken(?PasswordResetToken $resetToken): self
    {
        if ($resetToken === null && $this->resetToken !== null) {
            $this->resetToken->setUser(null);
        }

        if ($resetToken !== null && $resetToken->getUser() !== $this) {
            $resetToken->setUser($this);
        }

        $this->resetToken = $resetToken;
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
            $feedback->setUser($this);
        }
        return $this;
    }

    public function removeFeedback(Feedback $feedback): self
    {
        if ($this->feedbacks->removeElement($feedback)) {
            if ($feedback->getUser() === $this) {
                $feedback->setUser(null);
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
            $question->setUser($this);
        }
        return $this;
    }

    public function removeQuestion(Question $question): self
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getUser() === $this) {
                $question->setUser(null);
            }
        }
        return $this;
    }

    // ==================== MÉTHODES POUR LE FORMULAIRE ====================

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    // ==================== MÉTHODES DE SÉCURITÉ ====================

/**
 * @see UserInterface
 */
// src/Entity/User/UserModel.php

public function getRoles(): array
{
    // ⚠️ IMPORTANT: Ne JAMAIS retourner un tableau vide
    // Chaque utilisateur DOIT avoir au moins ROLE_USER
    
    $roles = ['ROLE_USER']; // ← TOUJOURS inclure ROLE_USER
    
    $roleName = $this->getRole()?->getRoleName() ?? '';
    $roleName = strtolower($roleName);
    
    if ($roleName === 'admin' || $this->roleId == 4) {
        $roles[] = 'ROLE_ADMIN';
    }
    if ($roleName === 'organisateur' || $this->roleId == 2) {
        $roles[] = 'ROLE_ORGANISATEUR';
    }
    if ($roleName === 'sponsor' || $this->roleId == 3) {
        $roles[] = 'ROLE_SPONSOR';
    }
    
    return $roles;
}

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getFormattedRegistrationDate(): string
    {
        if (!$this->registrationDate) {
            return 'Non disponible';
        }
        return $this->registrationDate->format('d/m/Y H:i');
    }

    public function getInitials(): string
    {
        $firstInitial = !empty($this->firstName) ? strtoupper(substr($this->firstName, 0, 1)) : '';
        $lastInitial = !empty($this->lastName) ? strtoupper(substr($this->lastName, 0, 1)) : '';

        if (empty($firstInitial) && empty($lastInitial)) {
            return 'U';
        }

        return $firstInitial . $lastInitial;
    }

    public function __toString(): string
    {
        return $this->getFullName() ?: $this->email;
    }
}