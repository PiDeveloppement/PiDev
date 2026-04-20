<?php

namespace App\Service\Ai;

use App\Repository\Budget\BudgetRepository;
use App\Repository\Depense\DepenseRepository;
use App\Repository\Sponsor\SponsorRepository;
use Doctrine\DBAL\Connection;

class ManagementContextProvider
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly DepenseRepository $depenseRepository,
        private readonly SponsorRepository $sponsorRepository,
        private readonly Connection $connection
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        $budgetTotals = $this->connection->fetchAssociative(
            'SELECT
                COUNT(*) AS budgetCount,
                COALESCE(SUM(initial_budget), 0) AS totalInitial,
                COALESCE(SUM(total_expenses), 0) AS totalExpenses,
                COALESCE(SUM(total_revenue), 0) AS totalRevenue,
                COALESCE(SUM(rentabilite), 0) AS totalRentabilite
             FROM budget'
        ) ?: [];

        $depenseTotals = $this->connection->fetchAssociative(
            'SELECT
                COUNT(*) AS depenseCount,
                COALESCE(SUM(amount), 0) AS totalAmount
             FROM depense'
        ) ?: [];

        $topCategories = $this->connection->fetchAllAssociative(
            'SELECT category, COALESCE(SUM(amount), 0) AS total
             FROM depense
             WHERE category IS NOT NULL AND category <> ""
             GROUP BY category
             ORDER BY total DESC, category ASC
             LIMIT 5'
        );

        $criticalBudgets = $this->connection->fetchAllAssociative(
            'SELECT b.id, b.event_id AS eventId, b.initial_budget AS initialBudget, b.total_expenses AS totalExpenses,
                    b.total_revenue AS totalRevenue, b.rentabilite AS rentabilite, e.title AS eventTitle
             FROM budget b
             LEFT JOIN event e ON e.id = b.event_id
             WHERE b.rentabilite < 0
             ORDER BY b.rentabilite ASC
             LIMIT 5'
        );

        return [
            'budgets' => [
                'count' => (int) ($budgetTotals['budgetCount'] ?? 0),
                'totalInitial' => (float) ($budgetTotals['totalInitial'] ?? 0),
                'totalExpenses' => (float) ($budgetTotals['totalExpenses'] ?? 0),
                'totalRevenue' => (float) ($budgetTotals['totalRevenue'] ?? 0),
                'totalRentabilite' => (float) ($budgetTotals['totalRentabilite'] ?? 0),
            ],
            'depenses' => [
                'count' => (int) ($depenseTotals['depenseCount'] ?? 0),
                'totalAmount' => (float) ($depenseTotals['totalAmount'] ?? 0),
                'topCategories' => array_map(
                    static fn (array $row): array => [
                        'category' => (string) ($row['category'] ?? 'N/A'),
                        'total' => (float) ($row['total'] ?? 0),
                    ],
                    $topCategories
                ),
            ],
            'sponsors' => [
                'count' => $this->sponsorRepository->getTotalSponsors(),
                'totalContribution' => $this->sponsorRepository->getTotalContribution(),
                'averageContribution' => $this->sponsorRepository->getAverageContribution(),
                'topCompanies' => $this->sponsorRepository->getTopCompaniesByContribution(5),
            ],
            'criticalBudgets' => array_map(
                static fn (array $row): array => [
                    'budgetId' => (int) ($row['id'] ?? 0),
                    'eventId' => (int) ($row['eventId'] ?? 0),
                    'eventTitle' => (string) ($row['eventTitle'] ?? 'Evenement inconnu'),
                    'initialBudget' => (float) ($row['initialBudget'] ?? 0),
                    'totalExpenses' => (float) ($row['totalExpenses'] ?? 0),
                    'totalRevenue' => (float) ($row['totalRevenue'] ?? 0),
                    'rentabilite' => (float) ($row['rentabilite'] ?? 0),
                ],
                $criticalBudgets
            ),
            'meta' => [
                'fetchedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'entities' => [
                    'Budget' => $this->budgetRepository->count([]),
                    'Depense' => $this->depenseRepository->count([]),
                    'Sponsor' => $this->sponsorRepository->count([]),
                ],
            ],
        ];
    }
}
