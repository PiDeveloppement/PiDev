<?php



namespace App\Entity\Depense;

use App\Entity\Budget\Budget;


use App\Repository\Depense\DepenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DepenseRepository::class)]
#[ORM\Table(name: 'depense')]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Budget::class)]
    #[ORM\JoinColumn(name: 'budget_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le budget est obligatoire.')]
    private ?Budget $budget = null;

    #[ORM\Column(length: 1000)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 1000,
        minMessage: 'La description doit contenir au moins 3 caracteres.',
        maxMessage: 'La description ne doit pas depasser 1000 caracteres.'
    )]
    #[Assert\Regex(
        pattern: '/[A-Za-zÀ-ÿ]/u',
        message: 'La description doit contenir des lettres et ne peut pas etre uniquement numerique.'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
    #[Assert\Positive(message: 'Le montant doit etre > 0.')]
    private string $amount = '';

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'La categorie est obligatoire.')]
    #[Assert\Length(max: 60, maxMessage: 'Categorie trop longue.')]
    private ?string $category = null;

    #[ORM\Column(name: 'expense_date', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date est obligatoire.')]
    private ?\DateTimeInterface $expenseDate = null;

    #[ORM\Column(name: 'original_currency', length: 3, options: ['default' => 'TND'])]
    #[Assert\Length(min: 3, max: 3, exactMessage: 'La devise doit contenir 3 lettres.')]
    private string $originalCurrency = 'TND';

    #[ORM\Column(name: 'original_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $originalAmount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBudget(): ?Budget
    {
        return $this->budget;
    }

    public function setBudget(?Budget $budget): self
    {
        $this->budget = $budget;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description !== null ? trim($description) : null;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(float|string $amount): self
    {
        if ($amount === '') {
            $this->amount = '';

            return $this;
        }

        $this->amount = number_format((float) $amount, 2, '.', '');

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category !== null ? trim($category) : null;

        return $this;
    }

    public function getExpenseDate(): ?\DateTimeInterface
    {
        return $this->expenseDate;
    }

    public function setExpenseDate(?\DateTimeInterface $expenseDate): self
    {
        $this->expenseDate = $expenseDate;

        return $this;
    }

    public function getOriginalCurrency(): string
    {
        return $this->originalCurrency;
    }

    public function setOriginalCurrency(string $originalCurrency): self
    {
        $this->originalCurrency = strtoupper(trim($originalCurrency));

        return $this;
    }

    public function getOriginalAmount(): ?string
    {
        return $this->originalAmount;
    }

    public function setOriginalAmount(float|string|null $originalAmount): self
    {
        $this->originalAmount = $originalAmount === null ? null : number_format((float) $originalAmount, 2, '.', '');

        return $this;
    }
}
