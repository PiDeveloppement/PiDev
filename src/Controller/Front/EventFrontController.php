<?php

namespace App\Controller\Front;

use App\Entity\Event\Event;
use App\Entity\Event\Category;
use App\Entity\Event\Ticket;
use App\Entity\User\UserModel;
use App\Repository\Event\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class EventFrontController extends AbstractController
{//    private EventService $eventService;
    #[Route('/events/public', name: 'app_public_events_legacy', methods: ['GET'])]
    public function legacyRedirect(): Response
    {
        return $this->redirectToRoute('app_public_events');
    }

    #[Route('/events', name: 'app_public_events', methods: ['GET'])]
    public function index(EventRepository $eventRepository, EntityManagerInterface $em): Response
    {
        $now = new \DateTimeImmutable();

        $events = $eventRepository
            ->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->andWhere('e.endDate >= :now')
            ->andWhere('e.status = :status')
            ->setParameter('now', $now)
            ->setParameter('status', Event::STATUS_PUBLISHED)
            ->orderBy('e.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        $categories = $em->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('front/events.html.twig', [
            'events' => $events,
            'categories' => $categories,
            'hasTickets' => $this->currentUserHasTickets($em),
        ]);
    }

    #[Route('/events/{id}', name: 'app_public_event_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(int $id, EventRepository $eventRepository, EntityManagerInterface $em): Response
    {
        $now = new \DateTimeImmutable();

        $event = $eventRepository
            ->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->andWhere('e.id = :id')
            ->andWhere('e.endDate >= :now')
            ->andWhere('e.status = :status')
            ->setParameter('id', $id)
            ->setParameter('now', $now)
            ->setParameter('status', Event::STATUS_PUBLISHED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$event) {
            throw $this->createNotFoundException('Evenement introuvable.');
        }

        $hasEventTicket = false;
        /** @var UserModel|null $user */
        $user = $this->getUser();
        if ($user instanceof UserModel) {
            $hasEventTicket = (int) $em->getRepository(Ticket::class)->count([
                'event' => $event,
                'user' => $user,
            ]) > 0;
        }

        return $this->render('front/event_show.html.twig', [
            'event' => $event,
            'hasTickets' => $this->currentUserHasTickets($em),
            'hasEventTicket' => $hasEventTicket,
        ]);
    }

    #[Route('/events/{id}/participate', name: 'app_public_event_participate', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function participate(
        int $id,
        Request $request,
        SessionInterface $session,
        EventRepository $eventRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var UserModel|null $user */
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            $session->set('pending_event', $id);
            $this->addFlash('info', 'Connectez-vous pour finaliser votre inscription.');
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('app_public_event_participation_confirm', ['id' => $id]);
    }

    #[Route('/events/{id}/participate/confirm', name: 'app_public_event_participation_confirm', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function confirmParticipation(
        int $id,
        Request $request,
        SessionInterface $session,
        EventRepository $eventRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var UserModel|null $user */
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            $session->set('pending_event', $id);
            $this->addFlash('info', 'Connectez-vous pour confirmer votre participation.');
            return $this->redirectToRoute('app_login');
        }

        $event = $eventRepository
            ->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->andWhere('e.id = :id')
            ->andWhere('e.endDate >= :now')
            ->andWhere('e.status = :status')
            ->setParameter('id', $id)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('status', Event::STATUS_PUBLISHED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$event instanceof Event) {
            $this->addFlash('error', 'Evenement indisponible.');
            return $this->redirectToRoute('app_public_events');
        }

        $ticketRepo = $em->getRepository(Ticket::class);
        $existingTicket = $ticketRepo->createQueryBuilder('t')
            ->andWhere('t.event = :event')
            ->andWhere('t.user = :user')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingTicket instanceof Ticket) {
            $session->remove('pending_event');
            $this->addFlash('info', 'Vous etes deja inscrit a cet evenement.');
            return $this->redirectToRoute('app_my_tickets');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('confirm_participation_event_' . $id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Action invalide, merci de reessayer.');
                return $this->redirectToRoute('app_public_event_participation_confirm', ['id' => $id]);
            }

            $ticketsForEvent = (int) $ticketRepo->count(['event' => $event]);
            if ((int) $event->getCapacity() > 0 && $ticketsForEvent >= (int) $event->getCapacity()) {
                $this->addFlash('warning', 'Desole, la capacite maximale est atteinte pour cet evenement.');
                return $this->redirectToRoute('app_public_event_show', ['id' => $id]);
            }

            $ticket = new Ticket();
            $ticket->setEvent($event);
            $ticket->setUser($user);
            $ticket->setTicketCode(Ticket::generateTicketCode((int) $event->getId(), (int) $user->getId()));

            $em->persist($ticket);
            $em->flush();
            $session->remove('pending_event');

            $this->addFlash('success', sprintf('Participation confirmee. Votre billet pour "%s" a ete cree automatiquement.', $event->getTitle()));
            return $this->redirectToRoute('app_my_tickets');
        }

        return $this->render('front/event_participation_confirm.html.twig', [
            'event' => $event,
            'hasTickets' => $this->currentUserHasTickets($em),
        ]);
    }

    #[Route('/my-tickets', name: 'app_my_tickets', methods: ['GET'])]
    public function myTickets(EntityManagerInterface $em): Response
    {
        /** @var UserModel|null $user */
        $user = $this->getUser();

        if (!$user instanceof UserModel) {
            $this->addFlash('info', 'Connectez-vous pour voir vos billets.');
            return $this->redirectToRoute('app_login');
        }

        $tickets = $em->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.event', 'e')
            ->addSelect('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('front/my_tickets.html.twig', [
            'tickets' => $tickets,
            'hasTickets' => count($tickets) > 0,
        ]);
    }

    #[Route('/my-tickets/{id}/cancel', name: 'app_my_ticket_cancel', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function cancelMyTicket(int $id, Request $request, EntityManagerInterface $em): Response
    {
        /** @var UserModel|null $user */
        $user = $this->getUser();

        if (!$user instanceof UserModel) {
            $this->addFlash('info', 'Connectez-vous pour gerer vos billets.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('cancel_ticket_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action invalide, merci de reessayer.');
            return $this->redirectToRoute('app_my_tickets');
        }

        $ticket = $em->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.event', 'e')
            ->addSelect('e')
            ->andWhere('t.id = :id')
            ->andWhere('t.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$ticket instanceof Ticket) {
            $this->addFlash('warning', 'Billet introuvable ou non autorise.');
            return $this->redirectToRoute('app_my_tickets');
        }

        if ((bool) $ticket->isUsed()) {
            $this->addFlash('warning', 'Ce billet est deja utilise et ne peut pas etre annule.');
            return $this->redirectToRoute('app_my_tickets');
        }

        $event = $ticket->getEvent();
        if ($event instanceof Event && $event->getStartDate() instanceof \DateTimeInterface && $event->getStartDate() <= new \DateTimeImmutable()) {
            $this->addFlash('warning', 'L evenement a deja commence, annulation impossible.');
            return $this->redirectToRoute('app_my_tickets');
        }

        $em->remove($ticket);
        $em->flush();

        $this->addFlash('success', 'Votre billet a ete annule avec succes.');

        return $this->redirectToRoute('app_my_tickets');
    }

    private function currentUserHasTickets(EntityManagerInterface $em): bool
    {
        /** @var UserModel|null $user */
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            return false;
        }

        return (int) $em->getRepository(Ticket::class)->count(['userId' => $user->getId()]) > 0;
    }
}
