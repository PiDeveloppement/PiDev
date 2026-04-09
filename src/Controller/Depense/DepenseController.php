<?php

namespace App\Controller\Depense;

use App\Entity\Budget\Budget;
use App\Entity\Depense\Depense;
use App\Entity\User\UserModel;
use App\Form\Depense\DepenseType;
use App\Repository\Budget\BudgetRepository;
use App\Repository\Depense\DepenseRepository;
use App\Service\Depense\DepenseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DepenseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepenseRepository $depenseRepository,
        private BudgetRepository $budgetRepository,
        private DepenseService $depenseService
    ) {
    }

    #[Route('/admin/depenses', name: 'app_depenses_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $search = trim((string) $request->query->get('q', ''));
        $budgetId = (int) $request->query->get('budget_id', 0);
        $category = trim((string) $request->query->get('category', ''));
        $period = (string) $request->query->get('period', 'all');
        $financialState = (string) $request->query->get('state', 'all');

        $qb = $this->depenseRepository->createQueryBuilder('d')
            ->leftJoin('d.budget', 'b')
            ->addSelect('b')
            ->orderBy('d.expenseDate', 'DESC')
            ->addOrderBy('d.id', 'DESC');

        if ($search !== '') {
            $budgetIds = $this->depenseService->findBudgetIdsByEventTitleLike($search);
            if ($budgetIds !== []) {
                $qb->andWhere('b.id IN (:budgetSearchIds)')->setParameter('budgetSearchIds', $budgetIds);
            } else {
                $qb->andWhere('1 = 0');
            }
        }

        if ($budgetId > 0) {
            $qb->andWhere('b.id = :budgetId')->setParameter('budgetId', $budgetId);
        }

        if ($category !== '') {
            $qb->andWhere('LOWER(d.category) = :category')->setParameter('category', mb_strtolower($category));
        }

        $range = $this->depenseService->resolvePeriodRange($period);
        if ($range !== null) {
            $qb->andWhere('d.expenseDate BETWEEN :fromDate AND :toDate')
                ->setParameter('fromDate', $range['from'])
                ->setParameter('toDate', $range['to']);
        }

        switch ($financialState) {
            case 'low':
                $qb->andWhere('d.amount < 100');
                break;
            case 'medium':
                $qb->andWhere('d.amount >= 100 AND d.amount <= 1000');
                break;
            case 'high':
                $qb->andWhere('d.amount > 1000');
                break;
        }

        /** @var Depense[] $depenses */
        $depenses = $qb->getQuery()->getResult();
        $budgets = $this->depenseService->fetchBudgets();
        $stats = $this->depenseService->buildStats($depenses);

        return $this->render('depense/index.html.twig', [
            'pageInfo' => ['title' => 'Gestion des depenses', 'subtitle' => 'CRUD, filtres et indicateurs'],
            'depenses' => $depenses,
            'budgets' => $budgets,
            'budgetDisplayMap' => $this->depenseService->buildBudgetDisplayMap($budgets),
            'search' => $search,
            'selectedBudgetId' => $budgetId > 0 ? $budgetId : null,
            'selectedCategory' => $category,
            'selectedPeriod' => $period,
            'selectedState' => $financialState,
            'categories' => $this->depenseService->fetchCategories(),
            'stats' => $stats,
        ]);
    }

    #[Route('/admin/depenses/new', name: 'app_depenses_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $depense = new Depense();
        $depense->setExpenseDate(new \DateTimeImmutable());
        $depense->setOriginalCurrency('TND');

        $form = $this->createForm(DepenseType::class, $depense, [
            'budget_choices' => $this->depenseService->buildBudgetChoices(),
            'category_choices' => $this->depenseService->buildCategoryChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $budgetId = (int) $form->get('budgetId')->getData();
            $budget = $this->budgetRepository->find($budgetId);

            if (!$budget instanceof Budget) {
                $this->addFlash('error', 'Budget invalide.');
            } else {
                $depense->setBudget($budget);
                $this->depenseService->normalizeDepenseCurrency($depense);

                $this->entityManager->persist($depense);
                $this->entityManager->flush();
                $this->depenseService->recomputeBudget($budget);
                $this->entityManager->flush();

                $this->addFlash('success', 'Depense ajoutee avec succes.');
                return $this->redirectToRoute('app_depenses_index');
            }
        }

        return $this->render('depense/form.html.twig', [
            'pageInfo' => ['title' => 'Nouvelle depense', 'subtitle' => 'Creation depense'],
            'form' => $form->createView(),
            'isEdit' => false,
        ]);
    }

    #[Route('/admin/depenses/{id}/edit', name: 'app_depenses_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Depense $depense): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $oldBudget = $depense->getBudget();
        $selectedBudgetId = $oldBudget?->getId();

        $form = $this->createForm(DepenseType::class, $depense, [
            'budget_choices' => $this->depenseService->buildBudgetChoices(),
            'category_choices' => $this->depenseService->buildCategoryChoices(),
            'selected_budget_id' => $selectedBudgetId,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $budgetId = (int) $form->get('budgetId')->getData();
            $budget = $this->budgetRepository->find($budgetId);

            if (!$budget instanceof Budget) {
                $this->addFlash('error', 'Budget invalide.');
            } else {
                $depense->setBudget($budget);
                $this->depenseService->normalizeDepenseCurrency($depense);

                $this->entityManager->flush();
                $this->depenseService->recomputeBudget($oldBudget);
                $this->depenseService->recomputeBudget($depense->getBudget());
                $this->entityManager->flush();

                $this->addFlash('success', 'Depense modifiee avec succes.');
                return $this->redirectToRoute('app_depenses_index');
            }
        }

        return $this->render('depense/form.html.twig', [
            'pageInfo' => ['title' => 'Modifier depense', 'subtitle' => 'Mise a jour depense'],
            'form' => $form->createView(),
            'isEdit' => true,
            'depense' => $depense,
        ]);
    }

    #[Route('/admin/depenses/{id}/details', name: 'app_depenses_details', methods: ['GET'])]
    public function details(Depense $depense): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        $budget = $depense->getBudget();
        $eventTitle = '-';
        if ($budget instanceof Budget && $budget->getEventId() !== null) {
            $eventTitle = $this->depenseService->fetchEventTitle((int) $budget->getEventId()) ?? ('Event #' . (int) $budget->getEventId());
        }

        return $this->render('depense/details.html.twig', [
            'pageInfo' => ['title' => 'Details depense', 'subtitle' => 'Informations depense'],
            'depense' => $depense,
            'eventTitle' => $eventTitle,
        ]);
    }

    #[Route('/admin/depenses/{id}', name: 'app_depenses_delete', methods: ['POST'])]
    public function delete(Request $request, Depense $depense): Response
    {
        $this->denyUnlessAdminOrOrganizer();

        if ($this->isCsrfTokenValid('delete_depense_' . $depense->getId(), (string) $request->request->get('_token'))) {
            $budget = $depense->getBudget();
            $this->entityManager->remove($depense);
            $this->entityManager->flush();
            $this->depenseService->recomputeBudget($budget);
            $this->entityManager->flush();
            $this->addFlash('success', 'Depense supprimee.');
        }

        return $this->redirectToRoute('app_depenses_index');
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
