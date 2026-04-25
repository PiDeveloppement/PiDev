<?php

namespace App\Service\Budget;

use App\Entity\Budget\Budget;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

class BudgetService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function initializeBudget(Budget $budget): void
    {
        // Un nouveau budget commence sans depense et avec une rentabilite calculee a zero.
        $budget->setTotalExpenses(0)
            ->setTotalRevenue(0)
            ->refreshRentabilite();
    }

    /** @param Budget[] $budgets */
    public function syncBudgetTotals(array $budgets): bool
    {
        // Rebalayer tous les budgets pour verifier si les depenses ont fait evoluer les totaux.
        $changed = false;
        foreach ($budgets as $budget) {
            $before = $budget->getTotalExpenses() . '|' . $budget->getRentabilite();
            $this->recomputeBudget($budget);
            $after = $budget->getTotalExpenses() . '|' . $budget->getRentabilite();
            if ($before !== $after) {
                $changed = true;
            }
        }

        return $changed;
    }

    public function recomputeBudget(?Budget $budget): void
    {
        // Recalcule le budget a partir de la somme reelle des depenses stockees en base.
        if (!$budget instanceof Budget) {
            return;
        }

        $budgetId = $budget->getId();
        $sum = 0.0;

        if ($budgetId !== null) {
            $value = $this->entityManager->getConnection()->fetchOne(
                'SELECT COALESCE(SUM(amount), 0) FROM depense WHERE budget_id = :id',
                ['id' => $budgetId]
            );
            $sum = (float) $value;
        }

        $budget->setTotalExpenses($sum);
        $budget->refreshRentabilite();
    }

    /** @return array<int,array{id:int,title:string,capacity:int,ticket_price:float}> */
    public function fetchEventRows(): array
    {
        // On expose seulement les champs utiles au pilotage budgetaire.
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, title, COALESCE(capacity, 0) AS capacity, COALESCE(ticket_price, 0) AS ticket_price FROM event ORDER BY start_date ASC'
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'capacity' => (int) ($row['capacity'] ?? 0),
            'ticket_price' => (float) ($row['ticket_price'] ?? 0),
        ], $rows);
    }

    /** @return array<string,int> */
    public function buildEventChoices(): array
    {
        $choices = [];
        foreach ($this->fetchEventRows() as $row) {
            $choices[$row['title']] = $row['id'];
        }

        return $choices;
    }

    /** @param int[] $eventIds @return array<int,string> */
    public function getEventTitleMap(array $eventIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, title FROM event WHERE id IN (?)',
            [$ids],
            [ArrayParameterType::INTEGER]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = (string) ($row['title'] ?? '-');
        }

        return $map;
    }

    /** @return array{id:int,title:string,capacity:int,ticket_price:float}|null */
    public function getEventInfo(int $eventId): ?array
    {
        if ($eventId <= 0) {
            return null;
        }

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id, title, COALESCE(capacity, 0) AS capacity, COALESCE(ticket_price, 0) AS ticket_price FROM event WHERE id = :id LIMIT 1',
            ['id' => $eventId]
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'capacity' => (int) ($row['capacity'] ?? 0),
            'ticket_price' => (float) ($row['ticket_price'] ?? 0),
        ];
    }

    public function countSoldTickets(int $eventId): int
    {
        if ($eventId <= 0) {
            return 0;
        }

        $count = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM event_ticket WHERE event_id = :eventId',
            ['eventId' => $eventId]
        );

        return (int) $count;
    }

    /** @return array{daysLeft:?int,adjustedRemaining:?float,forecastText:string} */
    public function buildForecast(int $budgetId, float $remaining, float $totalExpenses): array
    {
        // Le forecast estime la duree de couverture du budget au rythme actuel des depenses.
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT expense_date, amount FROM depense WHERE budget_id = :id ORDER BY expense_date ASC',
            ['id' => $budgetId]
        );

        $daysLeft = null;
        $adjustedRemaining = null;
        $forecastText = sprintf('Budget restant: %.2f DT', $remaining);

        if ($remaining < 0) {
            $forecastText = sprintf('Depassement budgetaire: %.2f DT', abs($remaining));
            return ['daysLeft' => $daysLeft, 'adjustedRemaining' => $adjustedRemaining, 'forecastText' => $forecastText];
        }

        if (count($rows) < 2 || $totalExpenses <= 0 || $remaining <= 0) {
            return ['daysLeft' => $daysLeft, 'adjustedRemaining' => $adjustedRemaining, 'forecastText' => $forecastText];
        }

        $firstDate = new \DateTimeImmutable((string) $rows[0]['expense_date']);
        $lastDate = new \DateTimeImmutable((string) $rows[count($rows) - 1]['expense_date']);
        $daysSpan = (int) $firstDate->diff($lastDate)->format('%a');

        if ($daysSpan <= 0) {
            return ['daysLeft' => $daysLeft, 'adjustedRemaining' => $adjustedRemaining, 'forecastText' => $forecastText];
        }

        $avgDaily = $totalExpenses / $daysSpan;
        if ($avgDaily <= 0) {
            return ['daysLeft' => $daysLeft, 'adjustedRemaining' => $adjustedRemaining, 'forecastText' => $forecastText];
        }

        $daysLeft = (int) floor($remaining / $avgDaily);
        $forecastText = sprintf('Le budget couvre environ %d jours au rythme actuel.', $daysLeft);

        if ($daysLeft > 0) {
            $annualInflation = 0.08;
            $dailyInflation = $annualInflation / 365;
            $adjustedRemaining = $remaining * max(0.0, (1 - ($daysLeft * $dailyInflation)));
        }

        return ['daysLeft' => $daysLeft, 'adjustedRemaining' => $adjustedRemaining, 'forecastText' => $forecastText];
    }

    /** @param Budget[] $budgets @return array{count:int,totalInitial:float,globalRent:float,deficitCount:int} */
    public function buildStats(array $budgets): array
    {
        // KPI globaux du tableau de bord budget.
        $count = count($budgets);
        $totalInitial = 0.0;
        $globalRent = 0.0;
        $deficitCount = 0;

        foreach ($budgets as $budget) {
            $totalInitial += (float) $budget->getInitialBudget();
            $rent = (float) $budget->getRentabilite();
            $globalRent += $rent;
            if ($rent < 0) {
                $deficitCount++;
            }
        }

        return [
            'count' => $count,
            'totalInitial' => $totalInitial,
            'globalRent' => $globalRent,
            'deficitCount' => $deficitCount,
        ];
    }

    public function passesFilters(Budget $budget, string $health, string $status): bool
    {
        if ($health !== 'all' && $this->getHealth($budget) !== $health) {
            return false;
        }

        $rent = (float) $budget->getRentabilite();
        if ($status === 'ok' && $rent < 0) {
            return false;
        }
        if ($status === 'deficit' && $rent >= 0) {
            return false;
        }

        return true;
    }

    public function getHealth(Budget $budget): string
    {
        // Classification simple pour colorer l'etat du budget dans l'interface.
        $initial = (float) $budget->getInitialBudget();
        $rent = (float) $budget->getRentabilite();

        if ($rent >= ($initial * 0.5)) {
            return 'excellent';
        }
        if ($rent >= 0) {
            return 'good';
        }
        if ($rent >= -($initial * 0.2)) {
            return 'fragile';
        }

        return 'critical';
    }
}
