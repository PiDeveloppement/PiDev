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
        // Cette methode fabrique le "contexte temps reel" injecte dans le prompt de l'assistant.
        $budgetTotals = $this->connection->fetchAssociative(
            'SELECT
                COUNT(*) AS budgetCount,
                COALESCE(SUM(initial_budget), 0) AS totalInitial,
                COALESCE(SUM(total_expenses), 0) AS totalExpenses,
                COALESCE(SUM(total_revenue), 0) AS totalRevenue,
                COALESCE(SUM(rentabilite), 0) AS totalRentabilite
             FROM budget'
        ) ?: [];

        // Aggregats depense globaux pour repondre aux questions de volume et de montant total.
        $depenseTotals = $this->connection->fetchAssociative(
            'SELECT
                COUNT(*) AS depenseCount,
                COALESCE(SUM(amount), 0) AS totalAmount
             FROM depense'
        ) ?: [];

        // Top categories depense pour les questions de repartition et de poids financier.
        $topCategories = $this->connection->fetchAllAssociative(
            'SELECT category, COALESCE(SUM(amount), 0) AS total
             FROM depense
             WHERE category IS NOT NULL AND category <> ""
             GROUP BY category
             ORDER BY total DESC, category ASC
             LIMIT 5'
        );

        // Budgets critiques: seulement ceux en rentabilite negative.
        $criticalBudgets = $this->connection->fetchAllAssociative(
            'SELECT b.id, b.event_id AS eventId, b.initial_budget AS initialBudget, b.total_expenses AS totalExpenses,
                    b.total_revenue AS totalRevenue, b.rentabilite AS rentabilite, e.title AS eventTitle
             FROM budget b
             LEFT JOIN event e ON e.id = b.event_id
             WHERE b.rentabilite < 0
             ORDER BY b.rentabilite ASC
             LIMIT 5'
        );

        // Snapshots budget pour pouvoir repondre sur un evenement ou un budget en particulier.
        $budgetSnapshots = $this->connection->fetchAllAssociative(
            'SELECT b.id AS budgetId, b.event_id AS eventId, e.title AS eventTitle,
                    b.initial_budget AS initialBudget, b.total_expenses AS totalExpenses,
                    b.total_revenue AS totalRevenue, b.rentabilite AS rentabilite
             FROM budget b
             LEFT JOIN event e ON e.id = b.event_id
             ORDER BY b.id DESC
             LIMIT 100'
        );

        // Contributions agregees par entreprise sponsor.
        $companyContributionRows = $this->connection->fetchAllAssociative(
            'SELECT company_name AS companyName, COALESCE(SUM(contribution_name), 0) AS total
             FROM sponsor
             WHERE company_name IS NOT NULL AND company_name <> ""
             GROUP BY company_name
             ORDER BY company_name ASC'
        );

        // Evenements associes a chaque entreprise sponsor.
        $companyEventRows = $this->connection->fetchAllAssociative(
            'SELECT s.company_name AS companyName, e.title AS eventTitle
             FROM sponsor s
             LEFT JOIN event e ON e.id = s.event_id
             WHERE s.company_name IS NOT NULL AND s.company_name <> ""
             ORDER BY s.company_name ASC, e.title ASC'
        );

        // Normaliser les montants sponsor en map simple: entreprise => total.
        $companyContributions = [];
        foreach ($companyContributionRows as $row) {
            $companyName = trim((string) ($row['companyName'] ?? ''));
            if ($companyName === '') {
                continue;
            }

            $companyContributions[$companyName] = (float) ($row['total'] ?? 0);
        }

        // Normaliser les evenements sponsorises en map simple: entreprise => [events].
        $companyEvents = [];
        foreach ($companyEventRows as $row) {
            $companyName = trim((string) ($row['companyName'] ?? ''));
            $eventTitle = trim((string) ($row['eventTitle'] ?? ''));
            if ($companyName === '' || $eventTitle === '') {
                continue;
            }

            if (!isset($companyEvents[$companyName])) {
                $companyEvents[$companyName] = [];
            }

            $companyEvents[$companyName][] = $eventTitle;
        }

        foreach ($companyEvents as $companyName => $events) {
            $companyEvents[$companyName] = array_values(array_unique($events));
        }

        // Le retour final regroupe les indicateurs par domaine metier + metadonnees de collecte.
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
                'companyContributions' => $companyContributions,
                'companyEvents' => $companyEvents,
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
            'budgetSnapshots' => array_map(
                static fn (array $row): array => [
                    'budgetId' => (int) ($row['budgetId'] ?? 0),
                    'eventId' => (int) ($row['eventId'] ?? 0),
                    'eventTitle' => (string) ($row['eventTitle'] ?? 'Evenement inconnu'),
                    'initialBudget' => (float) ($row['initialBudget'] ?? 0),
                    'totalExpenses' => (float) ($row['totalExpenses'] ?? 0),
                    'totalRevenue' => (float) ($row['totalRevenue'] ?? 0),
                    'rentabilite' => (float) ($row['rentabilite'] ?? 0),
                ],
                $budgetSnapshots
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