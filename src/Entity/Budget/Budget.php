<?php

namespace App\Entity\Budget;

use App\Repository\Budget\BudgetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\Table(name: 'budget')]
class Budget
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'event_id', type: Types::INTEGER)]
    #[Assert\Positive(message: 'L\'evenement est obligatoire.')]
    private ?int $eventId = null;

    #[ORM\Column(name: 'initial_budget', type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le budget initial est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le budget initial doit etre positif.')]
    private string $initialBudget = '0.00';

    #[ORM\Column(name: 'total_expenses', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero(message: 'Les depenses doivent etre positives.')]
    private string $totalExpenses = '0.00';

    #[ORM\Column(name: 'total_revenue', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero(message: 'Les revenus doivent etre positifs.')]
    private string $totalRevenue = '0.00';

    #[ORM\Column(name: 'rentabilite', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $rentabilite = '0.00';

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

    public function getInitialBudget(): string
    {
        return $this->initialBudget;
    }

    public function setInitialBudget(float|string $initialBudget): self
    {
        $this->initialBudget = number_format((float) $initialBudget, 2, '.', '');

        return $this;
    }

    public function getTotalExpenses(): string
    {
        return $this->totalExpenses;
    }

    public function setTotalExpenses(float|string $totalExpenses): self
    {
        $this->totalExpenses = number_format((float) $totalExpenses, 2, '.', '');

        return $this;
    }

    public function getTotalRevenue(): string
    {
        return $this->totalRevenue;
    }

    public function setTotalRevenue(float|string $totalRevenue): self
    {
        $this->totalRevenue = number_format((float) $totalRevenue, 2, '.', '');

        return $this;
    }

    public function getRentabilite(): string
    {
        return $this->rentabilite;
    }

    public function setRentabilite(float|string $rentabilite): self
    {
        $this->rentabilite = number_format((float) $rentabilite, 2, '.', '');

        return $this;
    }

    public function refreshRentabilite(): self
    {
        $this->setRentabilite((float) $this->totalRevenue - (float) $this->totalExpenses);

        return $this;
    }
}




