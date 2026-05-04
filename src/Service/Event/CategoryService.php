<?php

namespace App\Service\Event;

use App\Entity\Event\Category;
use App\Repository\Event\CategoryRepository;
use App\Repository\Event\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

class CategoryService
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function getBackOfficeListData(int $page, PaginatorInterface $paginator, array $filters = []): array
    {
        $categories = $paginator->paginate(
            $this->categoryRepository->createBackOfficeListQueryBuilder($filters),
            $page,
            6,
            ['wrap-queries' => true]
        );

        $filters = array_merge([
            'search' => '',
            'status' => '',
            'color' => '',
            'order' => 'recent',
        ], $filters);

        return [
            'categories' => $categories,
            'totalCategories' => $categories->getTotalItemCount(),
            'filteredCategories' => $categories->getTotalItemCount(),
            'totalEvents' => $this->eventRepository->count([]),
            'filters' => $filters,
            'availableColors' => $this->categoryRepository->findDistinctColors(),
        ];
    }

    public function createFromRequestData(array $data): void
    {
        $category = new Category();
        $this->mapRequestDataToCategory($category, $data, true);

        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    public function updateFromRequestData(Category $category, array $data): void
    {
        $this->mapRequestDataToCategory($category, $data, false);
        $this->entityManager->flush();
    }

    public function hydrateCategoryFromRequestData(Category $category, array $data, bool $isNew): void
    {
        $this->mapRequestDataToCategory($category, $data, $isNew);
    }

    public function delete(Category $category): void
    {
        $categoryId = $category->getId();
        if ($categoryId !== null) {
            $linkedEventsCount = $this->eventRepository->countByCategoryId($categoryId);
            if ($linkedEventsCount > 0) {
                throw new \RuntimeException(sprintf(
                    'Impossible de supprimer cette catégorie: %d événement(s) y sont encore rattachés.',
                    $linkedEventsCount
                ));
            }
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    public function getAllForExport(): array
    {
        return $this->categoryRepository->findAll();
    }

    private function mapRequestDataToCategory(Category $category, array $data, bool $isNew): void
    {
        $category->setName((string) ($data['name'] ?? ''));
        $category->setDescription((string) ($data['description'] ?? ''));
        $category->setIcon((string) ($data['icon'] ?? '📌'));
        $category->setColor((string) ($data['color'] ?? '#2196F3'));
        $category->setIsActive(($data['isActive'] ?? null) == '1');

        if ($isNew) {
            $category->setCreatedAt(new \DateTime());
        } else {
            $category->setUpdatedAt(new \DateTime());
        }
    }
}