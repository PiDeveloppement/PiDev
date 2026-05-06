<?php

namespace App\Controller\Event;

use App\Entity\Event\Category;
use App\Service\Event\CategoryService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Snappy\Pdf;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/category')]
class CategoryController extends AbstractController
{
    public function __construct(private CategoryService $categoryService, private ValidatorInterface $validator)
    {
    }

    #[Route('/', name: 'app_category_index', methods: ['GET'])]
    public function index(PaginatorInterface $paginator, Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query->get('search', '')),
            'status' => (string) $request->query->get('status', ''),
            'color' => (string) $request->query->get('color', ''),
            'order' => (string) $request->query->get('order', 'recent'),
        ];

        $listData = $this->categoryService->getBackOfficeListData(
            $request->query->getInt('page', 1),
            $paginator,
            $filters
        );

        $viewData = [
            'categories' => $listData['categories'],
            'totalCategories' => $listData['totalCategories'],
            'filteredCategories' => $listData['filteredCategories'],
            'totalEvents' => $listData['totalEvents'],
            'filters' => $listData['filters'],
            'availableColors' => $listData['availableColors'],
            'pageInfo' => [
                'title' => 'Gestion des catégories',
                'subtitle' => 'Gérez les catégories d\'événements',
            ],
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('category/index.html.twig', $viewData);
        }

        return $this->render('category/index.html.twig', $viewData);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Category();

        if ($request->isMethod('POST')) {
            $this->categoryService->hydrateCategoryFromRequestData($category, $request->request->all(), true);
            $violations = $this->validator->validate($category);

            if (count($violations) === 0 && $category->isValid()) {
                $this->categoryService->createFromRequestData($request->request->all());

                $this->addFlash('success', 'Catégorie créée avec succès.');
                return $this->redirectToRoute('app_category_index');
            }

            return $this->render('category/new.html.twig', [
                'category' => $category,
                'pageInfo' => [
                    'title' => 'Créer une catégorie',
                    'subtitle' => 'Ajouter une nouvelle catégorie',
                ],
                'errors' => $this->groupViolations($violations),
            ]);
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'pageInfo' => [
                'title' => 'Créer une catégorie',
                'subtitle' => 'Ajouter une nouvelle catégorie',
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Category $category): Response
    {
        return $this->render('category/show.html.twig', [
            'category' => $category,
            'pageInfo' => [
                'title' => $category->getName(),
                'subtitle' => 'Détails de la catégorie',
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(Category $category, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $this->categoryService->hydrateCategoryFromRequestData($category, $request->request->all(), false);
            $violations = $this->validator->validate($category);

            if (count($violations) === 0 && $category->isValid()) {
                $this->categoryService->updateFromRequestData($category, $request->request->all());

                $this->addFlash('success', 'Catégorie mise à jour avec succès.');
                return $this->redirectToRoute('app_category_index');
            }

            return $this->render('category/edit.html.twig', [
                'category' => $category,
                'pageInfo' => [
                    'title' => 'Modifier la catégorie',
                    'subtitle' => $category->getName(),
                ],
                'errors' => $this->groupViolations($violations),
            ]);
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'pageInfo' => [
                'title' => 'Modifier la catégorie',
                'subtitle' => $category->getName(),
            ],
        ]);
    }

    #[Route('/export-pdf', name: 'app_category_export_pdf', methods: ['GET'])]
    public function exportPdf(Pdf $pdf): Response
    {
        $categories = $this->categoryService->getAllForExport();
        $html = $this->renderView('category/pdf.html.twig', [
            'categories' => $categories,
            ]);
        $filename = 'categories_' . date('Y-m-d') . '.pdf';
        return new Response(
        $pdf->getOutputFromHtml($html),
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]
        );
    }

    #[Route('/{id}/delete', name: 'app_category_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Category $category): Response
    {
        try {
            $this->categoryService->delete($category);
            $this->addFlash('success', 'Catégorie supprimée avec succès.');
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            $message = 'Suppression impossible: cette catégorie est encore utilisée par des événements.';
            if (!str_contains($exception->getMessage(), 'FK_EVENT_CATEGORY')) {
                $message = 'Une erreur est survenue pendant la suppression.';
            }
            $this->addFlash('error', $message);
        }

        return $this->redirectToRoute('app_category_index');
    }

    /**
     * @param iterable<\Symfony\Component\Validator\ConstraintViolationInterface> $violations
     * @return array<string, array<int, string>>
     */
    private function groupViolations(iterable $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath();
            $errors[$field][] = $violation->getMessage();
        }

        return $errors;
    }
}