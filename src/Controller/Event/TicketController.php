<?php

namespace App\Controller\Event;

use App\Entity\Event\Ticket;
use App\Repository\Event\TicketRepository;
use App\Service\Event\TicketService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ticket')]
class TicketController extends AbstractController
{
    public function __construct(private TicketService $ticketService)
    {
    }

    #[Route('/', name: 'app_ticket_index', methods: ['GET'])]
    public function index(
        Request $request,
        PaginatorInterface $paginator
    ): Response {
        $search = trim((string) $request->query->get('search', ''));
        $eventFilter = $request->query->getString('event') ?: null;
        $statusFilter = $request->query->getString('status') ?: null;

        $listData = $this->ticketService->getBackOfficeListData(
            $search,
            $eventFilter,
            $statusFilter,
            $request->query->getInt('page', 1),
            6,
            $paginator
        );

        return $this->render('ticket/index.html.twig', [
            'tickets' => $listData['tickets'],
            'events' => $listData['events'],
            'totalTickets' => $listData['totalTickets'],
            'totalEvents' => $listData['totalEvents'],
            'search' => $search,
            'eventFilter' => $eventFilter,
            'statusFilter' => $statusFilter,
            'pageInfo' => [
                'title' => 'Gestion des billets',
                'subtitle' => 'Billets issus des participations front office',
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_ticket_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(int $id, TicketRepository $ticketRepository, EntityManagerInterface $entityManager): Response
    {
        $ticket = $ticketRepository->find($id);
        if (!$ticket instanceof Ticket) {
            $this->addFlash('warning', 'Billet introuvable.');

            return $this->redirectToRoute('app_ticket_index');
        }

        if (!$ticket->getQrCode()) {
            $ticket->setQrCode($this->generateUniqueQrToken($entityManager));
            $entityManager->flush();
        }

        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
            'pageInfo' => [
                'title' => 'Gestion des billets',
                'subtitle' => 'Suivi des billets et inscriptions',
            ],
        ]);
    }

    #[Route('/{id}/delete', name: 'app_ticket_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(int $id, TicketRepository $ticketRepository): Response
    {
        $ticket = $ticketRepository->find($id);
        if (!$ticket instanceof Ticket) {
            $this->addFlash('warning', 'Billet introuvable ou deja supprime.');

            return $this->redirectToRoute('app_ticket_index');
        }

        $this->ticketService->deleteTicket($ticket);

        $this->addFlash('success', 'Billet supprimé avec succès.');

        return $this->redirectToRoute('app_ticket_index');
    }

    private function generateUniqueQrToken(EntityManagerInterface $entityManager): string
    {
        do {
            $token = 'tkt_' . bin2hex(random_bytes(24));
            $exists = (int) $entityManager->getRepository(Ticket::class)->count(['qrCode' => $token]) > 0;
        } while ($exists);

        return $token;
    }
}