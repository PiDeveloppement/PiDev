<?php

namespace App\Service\Event;

use App\Entity\Event\Ticket;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

class TicketService
{
    public function __construct(
        private TicketRepository $ticketRepository,
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param string|null $eventFilter
     * @param string|null $statusFilter
     * @return array<string, mixed>
     */
    public function getBackOfficeListData(
        string $search,
        $eventFilter,
        $statusFilter,
        int $page,
        int $perPage,
        PaginatorInterface $paginator
    ): array {
        $qb = $this->ticketRepository->createFilteredQueryBuilder($search, $eventFilter, $statusFilter);

        $tickets = $paginator->paginate(
            $qb,
            $page,
            $perPage,
            ['wrap-queries' => true]
        );

        return [
            'tickets' => $tickets,
            'events' => $this->eventRepository->findAll(),
            'totalTickets' => $this->ticketRepository->countAll(),
            'totalEvents' => $this->ticketRepository->countUniqueEvents(),
        ];
    }

    public function deleteTicket(Ticket $ticket): void
    {
        $this->entityManager->remove($ticket);
        $this->entityManager->flush();
    }
}
