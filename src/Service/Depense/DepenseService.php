<?php

namespace App\Service\Depense;

use App\Entity\Budget\Budget;
use App\Entity\Depense\Depense;
use App\Repository\Budget\BudgetRepository;
use App\Service\Budget\BudgetService;
use App\Service\Currency\CurrencyConverterService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

class DepenseService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BudgetRepository $budgetRepository,
        private BudgetService $budgetService,
        private CurrencyConverterService $currencyConverter
    ) {
    }

    /** @return Budget[] */
    public function fetchBudgets(): array
    {
        // Les budgets sont proposes du plus recent au plus ancien dans les formulaires depense.
        return $this->budgetRepository->createQueryBuilder('b')->orderBy('b.id', 'DESC')->getQuery()->getResult();
    }

    /** @return array<string,int> */
    public function buildBudgetChoices(): array
    {
        // Les labels budget affichent le titre evenement + budget initial pour guider l'utilisateur.
        $budgets = $this->fetchBudgets();
        $eventTitleMap = $this->fetchEventTitleMap(array_map(static fn (Budget $budget): int => (int) $budget->getEventId(), $budgets));

        $choices = [];
        foreach ($budgets as $budget) {
            $eventId = (int) $budget->getEventId();
            $eventTitle = $eventTitleMap[$eventId] ?? ('Event ' . $eventId);
            $label = sprintf('%s (%.2f DT)', $eventTitle, (float) $budget->getInitialBudget());
            $choices[$label] = (int) $budget->getId();
        }

        return $choices;
    }

    /** @return array<string,string> */
    public function buildCategoryChoices(): array
    {
        $choices = [];
        foreach ($this->fetchCategories() as $category) {
            $choices[$category] = $category;
        }

        return $choices;
    }

    /** @return string[] */
    public function fetchCategories(): array
    {
        // Priorite aux categories officielles d'evenement, puis fallback sur l'historique de depense.
        $rows = $this->entityManager->getConnection()->fetchFirstColumn('SELECT name FROM event_category ORDER BY name');
        $categories = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $rows), static fn (string $value): bool => $value !== ''));

        if ($categories === []) {
            $fallback = $this->entityManager->getConnection()->fetchFirstColumn('SELECT DISTINCT category FROM depense WHERE category IS NOT NULL AND category <> "" ORDER BY category');
            $categories = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $fallback), static fn (string $value): bool => $value !== ''));
        }

        return $categories;
    }

    /** @param Depense[] $depenses @return array{count:int,total:float,average:float,categories:int,byCategory:array<string,float>} */
    public function buildStats(array $depenses): array
    {
        // KPI depense: volume, total, moyenne et repartition par categorie.
        $count = count($depenses);
        $total = 0.0;
        $byCategory = [];

        foreach ($depenses as $depense) {
            $amount = (float) $depense->getAmount();
            $total += $amount;
            $category = trim((string) ($depense->getCategory() ?? ''));
            if ($category === '') {
                $category = 'Sans categorie';
            }
            $byCategory[$category] = ($byCategory[$category] ?? 0.0) + $amount;
        }

        arsort($byCategory);

        return [
            'count' => $count,
            'total' => $total,
            'average' => $count > 0 ? $total / $count : 0.0,
            'categories' => count($byCategory),
            'byCategory' => $byCategory,
        ];
    }

    /** @param Budget[] $budgets @return array<int,string> */
    public function buildBudgetDisplayMap(array $budgets): array
    {
        $eventTitleMap = $this->fetchEventTitleMap(array_map(static fn (Budget $budget): int => (int) $budget->getEventId(), $budgets));

        $map = [];
        foreach ($budgets as $budget) {
            $eventId = (int) $budget->getEventId();
            $eventTitle = $eventTitleMap[$eventId] ?? ('Event ' . $eventId);
            $map[(int) $budget->getId()] = sprintf('%s (%.2f DT)', $eventTitle, (float) $budget->getInitialBudget());
        }

        return $map;
    }

    /** @param int[] $eventIds @return array<int,string> */
    public function fetchEventTitleMap(array $eventIds): array
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

    public function fetchEventTitle(int $eventId): ?string
    {
        if ($eventId <= 0) {
            return null;
        }

        $value = $this->entityManager->getConnection()->fetchOne(
            'SELECT title FROM event WHERE id = :id LIMIT 1',
            ['id' => $eventId]
        );

        return $value !== false ? (string) $value : null;
    }

    /** @return int[] */
    public function findBudgetIdsByEventTitleLike(string $search): array
    {
        $rows = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT b.id FROM budget b JOIN event e ON e.id = b.event_id WHERE LOWER(e.title) LIKE :q',
            ['q' => '%' . mb_strtolower(trim($search)) . '%']
        );

        return array_values(array_map('intval', $rows));
    }

    /** @return array{from:\DateTimeImmutable,to:\DateTimeImmutable}|null */
    public function resolvePeriodRange(string $period): ?array
    {
        // Convertit un filtre metier ("month", "quarter", "year") en intervalle de dates.
        $today = new \DateTimeImmutable('today');

        return match ($period) {
            'month' => ['from' => $today->modify('first day of this month'), 'to' => $today->modify('last day of this month')],
            'quarter' => $this->resolveQuarterRange($today),
            'year' => ['from' => new \DateTimeImmutable($today->format('Y-01-01')), 'to' => new \DateTimeImmutable($today->format('Y-12-31'))],
            default => null,
        };
    }

    public function normalizeDepenseCurrency(Depense $depense): void
    {
        // Toutes les depenses sont stockees en TND, avec conservation du montant/devise d'origine si necessaire.
        $currency = strtoupper(trim((string) $depense->getOriginalCurrency()));
        $inputAmount = (float) $depense->getAmount();

        if ($currency === '') {
            $currency = 'TND';
        }

        if ($currency === 'TND') {
            $depense->setOriginalCurrency('TND');
            $depense->setOriginalAmount(null);
            $depense->setAmount($inputAmount);
            return;
        }

        $depense->setOriginalCurrency($currency);
        $depense->setOriginalAmount($inputAmount);
        $depense->setAmount($this->currencyConverter->convert($inputAmount, $currency, 'TND'));
    }

    public function recomputeBudget(?Budget $budget): void
    {
        $this->budgetService->recomputeBudget($budget);
    }

    /** @return array{from:\DateTimeImmutable,to:\DateTimeImmutable} */
    private function resolveQuarterRange(\DateTimeImmutable $today): array
    {
        // Calcule debut/fin du trimestre courant pour le filtrage des depenses.
        $month = (int) $today->format('n');
        $year = (int) $today->format('Y');
        $startMonth = ((int) floor(($month - 1) / 3) * 3) + 1;
        $from = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $startMonth));
        $to = $from->modify('+2 months')->modify('last day of this month');

        return ['from' => $from, 'to' => $to];
    }

}
