<?php

namespace App\Service\Event;

use App\Entity\Event\Category;
use App\Entity\Event\Event;
use App\Repository\Event\CategoryRepository;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

class EventService
{
    public function __construct(
        private EventRepository $eventRepository,
        private CategoryRepository $categoryRepository,
        private TicketRepository $ticketRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function getBackOfficeListData(int $page, PaginatorInterface $paginator): array
    {
        $allEvents = $this->eventRepository->findAll();

        $events = $paginator->paginate(
            $this->eventRepository->createBackOfficeListQueryBuilder(),
            $page,
            8,
            ['wrap-queries' => true]
        );

        $now = new \DateTimeImmutable();
        $totalAvenir = 0;
        $totalTermine = 0;

        foreach ($allEvents as $event) {
            if ($event->getStartDate() && $event->getStartDate() > $now) {
                $totalAvenir++;
            }
            if ($event->getEndDate() && $event->getEndDate() < $now) {
                $totalTermine++;
            }
        }

        return [
            'events' => $events,
            'categories' => $this->categoryRepository->findAllOrderedByName(),
            'totalEvents' => count($allEvents),
            'totalTickets' => $this->ticketRepository->countAll(),
            'totalAvenir' => $totalAvenir,
            'totalTermine' => $totalTermine,
        ];
    }

    public function getCategoriesForSelect(): array
    {
        return $this->categoryRepository->findAllOrderedByName();
    }

    public function resolveCategoryById(?int $categoryId): ?Category
    {
        if ($categoryId === null || $categoryId <= 0) {
            return null;
        }

        return $this->categoryRepository->find($categoryId);
    }

    public function validatePaidEventPrice(Event $event): ?string
    {
        if (!$event->isIsFree() && (float) $event->getTicketPrice() <= 0) {
            return 'Le prix doit etre superieur a 0 pour un evenement payant.';
        }

        return null;
    }

    public function createEvent(Event $event, ?Category $category): void
    {
        $this->prepareEventBeforeSave($event, $category, true);
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    public function updateEvent(Event $event, ?Category $category): void
    {
        $this->prepareEventBeforeSave($event, $category, false);
        $this->entityManager->flush();
    }

    public function deleteEvent(Event $event): void
    {
        $this->entityManager->remove($event);
        $this->entityManager->flush();
    }

    public function getEventsForExport(): array
    {
        return $this->eventRepository->findAllOrderedByDate();
    }

    private function prepareEventBeforeSave(Event $event, ?Category $category, bool $isNew): void
    {
        $event->setCategory($category);
        $event->setCategoryId($category?->getId());

        if ($isNew) {
            if ($event->getStatus() === null) {
                $event->setStatus(Event::STATUS_DRAFT);
            }
            if ($event->getCreatedAt() === null) {
                $event->setCreatedAt(new \DateTime());
            }
        } else {
            $event->setUpdatedAt(new \DateTime());
        }

        if ($event->isIsFree()) {
            $event->setTicketPrice(0.0);
        }
    }
}
