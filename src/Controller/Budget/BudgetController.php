<?php

namespace App\Controller\Budget;

use App\Entity\Budget\Budget;
use App\Entity\User\UserModel;
use App\Form\Budget\BudgetType;
use App\Repository\Budget\BudgetRepository;
use App\Service\Budget\BudgetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BudgetController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BudgetRepository $budgetRepository,
        private BudgetService $budgetService
    ) {
    }

    #[Route('/admin/budget', name: 'app_budget_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $eventId = (int) $request->query->get('event_id', 0);
        $health = (string) $request->query->get('health', 'all');
        $status = (string) $request->query->get('status', 'all');
        $search = (string) $request->query->get('search', '');
        $sort = (string) $request->query->get('sort', 'default');
        $state = (string) $request->query->get('state', 'all');

        $qb = $this->budgetRepository->createQueryBuilder('b')->orderBy('b.id', 'DESC');
        if ($eventId > 0) {
            $qb->andWhere('b.eventId = :eventId')->setParameter('eventId', $eventId);
        }

        /** @var Budget[] $budgets */
        $budgets = $qb->getQuery()->getResult();
        if ($this->budgetService->syncBudgetTotals($budgets)) {
            $this->entityManager->flush();
        }

        $budgets = array_values(array_filter($budgets, fn (Budget $budget): bool => $this->budgetService->passesFilters($budget, $health, $status)));

        // Get event title map for searching
        $eventTitleMap = $this->budgetService->getEventTitleMap(array_map(static fn (Budget $budget): int => (int) $budget->getEventId(), $budgets));

        // Filter by search term
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $budgets = array_filter($budgets, function (Budget $budget) use ($searchLower, $eventTitleMap) {
                $eventTitle = $eventTitleMap[(int) $budget->getEventId()] ?? '';
                return stripos($eventTitle, $searchLower) !== false;
            });
            $budgets = array_values($budgets);
        }

        // Filter by state
        if ($state !== 'all' && !empty($state)) {
            $budgets = array_filter($budgets, function (Budget $budget) use ($state) {
                $ini = (float) $budget->getInitialBudget();
                $ren = (float) $budget->getRentabilite();
                
                $hClass = 'good';
                if ($ren >= $ini * 0.5) {
                    $hClass = 'excellent';
                } elseif ($ren < 0) {
                    $hClass = 'critical';
                } elseif ($ren < $ini * 0.2) {
                    $hClass = 'fragile';
                }
                
                return $hClass === $state;
            });
            $budgets = array_values($budgets);
        }

        // Apply sorting
        usort($budgets, function (Budget $a, Budget $b) use ($sort) {
            switch ($sort) {
                case 'name-asc':
                    return strcasecmp($a->getEventId(), $b->getEventId());
                case 'name-desc':
                    return strcasecmp($b->getEventId(), $a->getEventId());
                case 'budget-asc':
                    return ((float) $a->getInitialBudget()) <=> ((float) $b->getInitialBudget());
                case 'budget-desc':
                    return ((float) $b->getInitialBudget()) <=> ((float) $a->getInitialBudget());
                case 'profitability-asc':
                    return ((float) $a->getRentabilite()) <=> ((float) $b->getRentabilite());
                case 'profitability-desc':
                    return ((float) $b->getRentabilite()) <=> ((float) $a->getRentabilite());
                default:
                    return 0;
            }
        });

        $stats = $this->budgetService->buildStats($budgets);

        $top5 = array_slice($budgets, 0, 5);
        $chartLabels = [];
        $chartInitial = [];
        $chartExpenses = [];
        $chartRevenue = [];

        foreach ($top5 as $budget) {
            $budgetEventId = (int) $budget->getEventId();
            $chartLabels[] = $eventTitleMap[$budgetEventId] ?? ('Event #' . $budgetEventId);
            $chartInitial[] = (float) $budget->getInitialBudget();
            $chartExpenses[] = (float) $budget->getTotalExpenses();
            $chartRevenue[] = (float) $budget->getTotalRevenue();
        }

        return $this->render('budget/index.html.twig', [
            'pageInfo' => ['title' => 'Gestion du budget', 'subtitle' => 'CRUD, KPI et previsions budget'],
            'budgets' => $budgets,
            'events' => $this->budgetService->fetchEventRows(),
            'eventTitleMap' => $eventTitleMap,
            'selectedEventId' => $eventId > 0 ? $eventId : null,
            'selectedHealth' => $health,
            'selectedStatus' => $status,
            'stats' => $stats,
            'chartLabels' => $chartLabels,
            'chartInitial' => $chartInitial,
            'chartExpenses' => $chartExpenses,
            'chartRevenue' => $chartRevenue,
            'search' => $search,
            'sort' => $sort,
            'state' => $state,
        ]);
    }

    #[Route('/admin/budget/suggestions', name: 'app_budget_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $q = (string) $request->query->get('q', '');
        
        if (strlen($q) < 1) {
            return $this->json([]);
        }

        $budgets = $this->budgetRepository->createQueryBuilder('b')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();

        $eventTitleMap = $this->budgetService->getEventTitleMap(
            array_map(static fn (Budget $budget): int => (int) $budget->getEventId(), $budgets)
        );

        $searchLower = strtolower($q);
        $suggestions = [];
        $seen = [];

        foreach ($budgets as $budget) {
            $eventTitle = $eventTitleMap[(int) $budget->getEventId()] ?? '';
            if (!empty($eventTitle) && stripos($eventTitle, $searchLower) !== false && !in_array($eventTitle, $seen)) {
                $suggestions[] = $eventTitle;
                $seen[] = $eventTitle;
            }
            if (count($suggestions) >= 10) {
                break;
            }
        }

        return $this->json($suggestions);
    }

    #[Route('/admin/budget/new', name: 'app_budget_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $budget = new Budget();
        $this->budgetService->initializeBudget($budget);

        $form = $this->createForm(BudgetType::class, $budget, [
            'event_choices' => $this->budgetService->buildEventChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $eventId = (int) $budget->getEventId();
            if ($eventId <= 0 || $this->budgetService->getEventInfo($eventId) === null) {
                $this->addFlash('error', 'Evenement invalide.');
            } elseif ($this->budgetRepository->existsForEvent($eventId)) {
                $this->addFlash('error', 'Un budget existe deja pour cet evenement.');
            } else {
                $this->budgetService->recomputeBudget($budget);
                $this->entityManager->persist($budget);
                $this->entityManager->flush();

                $this->addFlash('success', 'Budget ajoute avec succes.');
                return $this->redirectToRoute('app_budget_index');
            }
        }

        return $this->render('budget/form.html.twig', [
            'pageInfo' => ['title' => 'Nouveau budget', 'subtitle' => 'Creation budget'],
            'form' => $form->createView(),
            'isEdit' => false,
        ]);
    }

    #[Route('/admin/budget/{id}/edit', name: 'app_budget_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Budget $budget): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $form = $this->createForm(BudgetType::class, $budget, [
            'event_choices' => $this->budgetService->buildEventChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $eventId = (int) $budget->getEventId();
            $budgetId = $budget->getId();
            if ($eventId <= 0 || $this->budgetService->getEventInfo($eventId) === null) {
                $this->addFlash('error', 'Evenement invalide.');
            } elseif ($this->budgetRepository->existsForEvent($eventId, $budgetId)) {
                $this->addFlash('error', 'Un autre budget existe deja pour cet evenement.');
            } else {
                $this->budgetService->recomputeBudget($budget);
                $this->entityManager->flush();

                $this->addFlash('success', 'Budget modifie avec succes.');
                return $this->redirectToRoute('app_budget_index');
            }
        }

        return $this->render('budget/form.html.twig', [
            'pageInfo' => ['title' => 'Modifier budget', 'subtitle' => 'Mise a jour budget'],
            'form' => $form->createView(),
            'isEdit' => true,
            'budget' => $budget,
        ]);
    }

    #[Route('/admin/budget/{id}/details', name: 'app_budget_details', methods: ['GET'])]
    public function details(Budget $budget): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $this->budgetService->recomputeBudget($budget);
        $this->entityManager->flush();

        $event = $this->budgetService->getEventInfo((int) $budget->getEventId());
        $remaining = (float) $budget->getInitialBudget() - (float) $budget->getTotalExpenses();
        $forecast = $this->budgetService->buildForecast((int) $budget->getId(), $remaining, (float) $budget->getTotalExpenses());

        $ticketPrice = (float) ($event['ticket_price'] ?? 0.0);
        $capacity = (int) ($event['capacity'] ?? 0);
        $soldTickets = $this->budgetService->countSoldTickets((int) $budget->getEventId());

        $participantsToTarget = $ticketPrice > 0 ? max(0, (int) ceil(((float) $budget->getInitialBudget() - (float) $budget->getTotalRevenue()) / $ticketPrice)) : null;
        $participantsToBreakEven = $ticketPrice > 0 ? max(0, (int) ceil(((float) $budget->getTotalExpenses() - (float) $budget->getTotalRevenue()) / $ticketPrice)) : null;

        $seatsLeft = max(0, $capacity - $soldTickets);
        $maxNeeded = max((int) ($participantsToTarget ?? 0), (int) ($participantsToBreakEven ?? 0));

        $capacityAlert = null;
        if ($ticketPrice <= 0) {
            $capacityAlert = 'Prix billet non defini dans la table event.';
        } elseif ($capacity <= 0) {
            $capacityAlert = 'Capacite de l evenement non definie.';
        } elseif ($maxNeeded > $seatsLeft) {
            $capacityAlert = sprintf('Alerte: besoin %d participants, places restantes %d.', $maxNeeded, $seatsLeft);
        } else {
            $capacityAlert = sprintf('Simulation valide: %d places restantes pour %d participants.', $seatsLeft, $maxNeeded);
        }

        return $this->render('budget/details.html.twig', [
            'pageInfo' => ['title' => 'Details budget', 'subtitle' => 'Previsions et simulation'],
            'budget' => $budget,
            'eventTitle' => (string) ($event['title'] ?? ('Event #' . (int) $budget->getEventId())),
            'remaining' => $remaining,
            'forecast' => $forecast,
            'ticketPrice' => $ticketPrice,
            'soldTickets' => $soldTickets,
            'participantsToTarget' => $participantsToTarget,
            'participantsToBreakEven' => $participantsToBreakEven,
            'capacityAlert' => $capacityAlert,
        ]);
    }

    #[Route('/admin/budget/{id}', name: 'app_budget_delete', methods: ['POST'])]
    public function delete(Request $request, Budget $budget): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        if ($this->isCsrfTokenValid('delete_budget_' . $budget->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($budget);
            $this->entityManager->flush();
            $this->addFlash('success', 'Budget supprime.');
        }

        return $this->redirectToRoute('app_budget_index');
    }

    private function denyUnlessAdminOrOrganizer(): void
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            throw $this->createAccessDeniedException('Acces reserve a l administration et aux organisateurs.');
        }

        // Vérifier via les rôles Symfony (plus fiable)
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_ORGANISATEUR', $roles, true)) {
            return;
        }

        // Vérification de secours via roleId (Admin=2, Organisateur=3)
        $roleId = (int) ($user->getRoleId() ?? 0);
        if ($roleId === 2 || $roleId === 3) {
            return;
        }

        throw $this->createAccessDeniedException('Acces reserve a l administration et aux organisateurs.');
    }
}
