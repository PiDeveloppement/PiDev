<?php

namespace App\Service\AI;

use App\Repository\Budget\BudgetRepository;
use App\Repository\Depense\DepenseRepository;
use App\Repository\Sponsor\SponsorRepository;
use Doctrine\DBAL\Connection;

class ManagementContextProvider
{
    private array $contextCache = [];

    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly DepenseRepository $depenseRepository,
        private readonly SponsorRepository $sponsorRepository,
        private readonly Connection $connection
    ) {
    }

    public function getContextForUser(int $userId): array
    {
        if (isset($this->contextCache[$userId])) {
            return $this->contextCache[$userId];
        }

        $context = [
            'user_id' => $userId,
            'budgets' => $this->getBudgetSummary(),
            'sponsors' => $this->getSponsorSummary(),
            'expenses' => $this->getExpenseSummary(),
        ];

        $this->contextCache[$userId] = $context;

        return $context;
    }

    public function updateContext(int $userId, array $context): void
    {
        $this->contextCache[$userId] = array_merge(
            $this->getContextForUser($userId),
            $context
        );
    }

    private function getBudgetSummary(): array
    {
        $budgets = $this->budgetRepository->findAll();
        
        return [
            'total' => count($budgets),
            'total_amount' => array_reduce($budgets, fn($sum, $b) => $sum + $b->getInitialBudget(), 0),
        ];
    }

    private function getSponsorSummary(): array
    {
        $sponsors = $this->sponsorRepository->findAll();
        
        return [
            'total' => count($sponsors),
            'total_contribution' => array_reduce($sponsors, fn($sum, $s) => $sum + $s->getContribution(), 0),
        ];
    }

    private function getExpenseSummary(): array
    {
        $expenses = $this->depenseRepository->findAll();
        
        return [
            'total' => count($expenses),
            'total_amount' => array_reduce($expenses, fn($sum, $e) => $sum + $e->getMontant(), 0),
        ];
    }
}
