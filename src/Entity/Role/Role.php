<?php
// src/Entity/Role.php

namespace App\Entity\Role;

use App\Entity\User\UserModel;
use App\Repository\Role\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: "role")]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: "Id_Role", type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "RoleName", length: 255, unique: true)]
    #[Assert\NotBlank(message: "Le nom du rôle est requis")]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: "Le nom du rôle doit faire au moins {{ limit }} caractères",
        maxMessage: "Le nom du rôle ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $roleName = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(mappedBy: 'role', targetEntity: UserModel::class)]
    private Collection $users;

    // ✅ UN SEUL CONSTRUCTEUR avec paramètre optionnel
    public function __construct(?string $roleName = null)
    {
        $this->users = new ArrayCollection();
        if ($roleName !== null) {
            $this->roleName = $roleName;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoleName(): ?string
    {
        return $this->roleName;
    }

    public function setRoleName(string $roleName): self
    {
        $this->roleName = $roleName;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(UserModel $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setRole($this);
        }
        return $this;
    }

    public function removeUser(UserModel $user): self
    {
        if ($this->users->removeElement($user)) {
            if ($user->getRole() === $this) {
                $user->setRole(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->roleName ?? 'Rôle';
    }
}