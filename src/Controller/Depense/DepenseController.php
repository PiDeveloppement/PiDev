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
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
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
        $this->denyUnlessAdmin();

        // 1) Lire les filtres de recherche et de segmentation des depenses.
        $search = trim((string) $request->query->get('q', ''));
        $budgetId = (int) $request->query->get('budget_id', 0);
        $category = trim((string) $request->query->get('category', ''));
        $period = (string) $request->query->get('period', 'all');
        $financialState = (string) $request->query->get('state', 'all');

        // 2) Construire la requete avec jointure budget pour afficher le contexte financier.
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

        // 3) Executer la requete puis calculer les stats qui alimentent la vue.
        /** @var Depense[] $depenses */
        $depenses = $qb->getQuery()->getResult();
        $budgets = $this->depenseService->fetchBudgets();
        $stats = $this->depenseService->buildStats($depenses);

        return $this->render('depense/index.html.twig', [
            'pageInfo' => ['title' => 'Gestion des depenses', 'subtitle' => 'Suivez les depenses, comparez les montants et gardez une vision claire des sorties financieres.'],
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
        $this->denyUnlessAdmin();

        // Initialiser une depense avec la date du jour et une devise par defaut.
        $depense = new Depense();
        $depense->setExpenseDate(new \DateTimeImmutable());
        $depense->setOriginalCurrency('TND');

        $form = $this->createForm(DepenseType::class, $depense, [
            'budget_choices' => $this->depenseService->buildBudgetChoices(),
            'category_choices' => $this->depenseService->buildCategoryChoices(),
        ]);
        $form->handleRequest($request);

        // On rattache explicitement l'objet Budget choisi dans le formulaire.
        if ($form->isSubmitted()) {
            $budgetId = (int) $form->get('budgetId')->getData();
            $budget = $budgetId > 0 ? $this->budgetRepository->find($budgetId) : null;

            if ($budget instanceof Budget) {
                $depense->setBudget($budget);
            } else {
                $depense->setBudget(null);
                $form->get('budgetId')->addError(new FormError('Budget invalide.'));
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($this->collectFormErrors($form) as $message) {
                $this->addFlash('error', $message);
            }
        }

        // Si le formulaire est valide, on convertit le montant si besoin puis on recalcule le budget.
        if ($form->isSubmitted() && $form->isValid()) {
            $budget = $depense->getBudget();

            if (!$budget instanceof Budget) {
                $this->addFlash('error', 'Budget invalide.');
            } else {
                try {
                    $this->depenseService->normalizeDepenseCurrency($depense);

                    $this->entityManager->persist($depense);
                    $this->entityManager->flush();
                    $this->depenseService->recomputeBudget($budget);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Depense ajoutee avec succes.');
                    return $this->redirectToRoute('app_depenses_index');
                } catch (\RuntimeException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }
        }

        return $this->render('depense/form.html.twig', [
            'pageInfo' => ['title' => 'Nouvelle depense', 'subtitle' => 'Ajoutez une depense avec sa categorie, sa date et sa conversion en dinar tunisien.'],
            'form' => $form->createView(),
            'isEdit' => false,
        ]);
    }

    #[Route('/admin/depenses/{id}/edit', name: 'app_depenses_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Depense $depense): Response
    {
        $this->denyUnlessAdmin();

        // On garde la trace de l'ancien budget pour le resynchroniser si la depense change de rattachement.
        $oldBudget = $depense->getBudget();
        $selectedBudgetId = $oldBudget?->getId();

        $form = $this->createForm(DepenseType::class, $depense, [
            'budget_choices' => $this->depenseService->buildBudgetChoices(),
            'category_choices' => $this->depenseService->buildCategoryChoices(),
            'selected_budget_id' => $selectedBudgetId,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $budgetId = (int) $form->get('budgetId')->getData();
            $budget = $budgetId > 0 ? $this->budgetRepository->find($budgetId) : null;

            if ($budget instanceof Budget) {
                $depense->setBudget($budget);
            } else {
                $depense->setBudget(null);
                $form->get('budgetId')->addError(new FormError('Budget invalide.'));
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($this->collectFormErrors($form) as $message) {
                $this->addFlash('error', $message);
            }
        }

        // L'edition suit la meme logique que la creation, avec resynchronisation des deux budgets.
        if ($form->isSubmitted() && $form->isValid()) {
            $budget = $depense->getBudget();

            if (!$budget instanceof Budget) {
                $this->addFlash('error', 'Budget invalide.');
            } else {
                try {
                    $this->depenseService->normalizeDepenseCurrency($depense);

                    $this->entityManager->flush();
                    $this->depenseService->recomputeBudget($oldBudget);
                    $this->depenseService->recomputeBudget($depense->getBudget());
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Depense modifiee avec succes.');
                    return $this->redirectToRoute('app_depenses_index');
                } catch (\RuntimeException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }
        }

        return $this->render('depense/form.html.twig', [
            'pageInfo' => ['title' => 'Modifier depense', 'subtitle' => 'Mettez a jour les informations d une depense pour conserver un suivi financier fiable.'],
            'form' => $form->createView(),
            'isEdit' => true,
            'depense' => $depense,
        ]);
    }

    #[Route('/admin/depenses/{id}/details', name: 'app_depenses_details', methods: ['GET'])]
    public function details(Depense $depense): Response
    {
        $this->denyUnlessAdmin();

        // La page detail affiche aussi le titre evenementiel du budget parent pour donner du contexte.
        $budget = $depense->getBudget();
        $eventTitle = '-';
        if ($budget instanceof Budget && $budget->getEventId() !== null) {
            $eventTitle = $this->depenseService->fetchEventTitle((int) $budget->getEventId()) ?? ('Event #' . (int) $budget->getEventId());
        }

        return $this->render('depense/details.html.twig', [
            'pageInfo' => ['title' => 'Details depense', 'subtitle' => 'Consultez toutes les informations de la depense et son contexte budgetaire.'],
            'depense' => $depense,
            'eventTitle' => $eventTitle,
        ]);
    }

    #[Route('/admin/depenses/{id}', name: 'app_depenses_delete', methods: ['POST'])]
    public function delete(Request $request, Depense $depense): Response
    {
        $this->denyUnlessAdmin();

        // Apres suppression, le budget parent est recalculé pour rester coherent.
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

    private function denyUnlessAdmin(): void
    {
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            throw $this->createAccessDeniedException('Acces reserve a l administration.');
        }

        // Vérifier via les rôles Symfony (plus fiable)
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return;
        }

        // Verification de secours via roleId (Administrateur=4)
        $roleId = (int) ($user->getRoleId() ?? 0);
        if ($roleId === 4) {
            return;
        }

        throw $this->createAccessDeniedException('Acces reserve a l administration.');
    }

    /**
     * @return string[]
     */
    private function collectFormErrors(FormInterface $form): array
    {
        // Normaliser les erreurs Symfony en messages flash lisibles dans l'interface.
        $messages = [];

        foreach ($form->getErrors(true, true) as $error) {
            $origin = $error->getOrigin();
            $fieldName = $origin instanceof FormInterface ? $origin->getName() : 'form';
            $messages[] = sprintf('Champ "%s": %s', $fieldName, $error->getMessage());
        }

        if ($messages === []) {
            return ['Creation impossible: un ou plusieurs champs sont invalides.'];
        }

        return array_values(array_unique($messages));
    }
}