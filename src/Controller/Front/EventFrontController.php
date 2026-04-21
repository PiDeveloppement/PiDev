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
{
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

        return $this->render('front/event_show.html.twig', [
            'event' => $event,
            'hasTickets' => $this->currentUserHasTickets($em),
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
        if (!$this->isCsrfTokenValid('participate_event_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action invalide, merci de reessayer.');
            return $this->redirectToRoute('app_public_event_show', ['id' => $id]);
        }

        /** @var UserModel|null $user */
        $user = $this->getUser();
        if (!$user instanceof UserModel) {
            $session->set('pending_event', $id);
            $session->set('after_login_redirect', 'app_my_tickets');
            $this->addFlash('info', 'Connectez-vous pour finaliser votre inscription.');
            return $this->redirectToRoute('app_login');
        }

        $event = $eventRepository
            ->createQueryBuilder('e')
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
            $this->addFlash('info', 'Vous etes deja inscrit a cet evenement.');
            return $this->redirectToRoute('app_my_tickets');
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

        $this->addFlash('success', 'Participation confirmee. Votre billet a ete cree automatiquement.');
        return $this->redirectToRoute('app_my_tickets');
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
