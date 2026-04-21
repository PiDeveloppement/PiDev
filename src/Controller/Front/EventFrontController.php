<?php

namespace App\Controller\Front;

use App\Entity\Event\Event;
use App\Entity\Event\Category;
use App\Entity\Event\Ticket;
use App\Entity\User\UserModel;
use App\Repository\Event\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Event\WeatherService;

class EventFrontController extends AbstractController
{//    private EventService $eventService;
    #[Route('/events/public', name: 'app_public_events_legacy', methods: ['GET'])]
    public function legacyRedirect(): Response
    {
        return $this->redirectToRoute('app_public_events');
    }

   #[Route('/participation/confirm', name: 'app_participation_confirm', methods: ['GET'])]
public function participationConfirm(
    SessionInterface $session,
    EventRepository $eventRepository,
    EntityManagerInterface $em
): Response {
    $eventId = $session->get('pending_event');
    $event = null;

    if ($eventId) {
        $event = $eventRepository->find($eventId);
    }

    return $this->render('front/participation_confirm_simple.html.twig', [
        'event' => $event,
        'hasTickets' => $this->currentUserHasTickets($em),
    ]);
}

    #[Route('/events/calendar', name: 'app_public_events_calendar', methods: ['GET'])]
    public function calendarView(): Response
    {
        return $this->render('front/events_calendar.html.twig');
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
    public function show(int $id, EventRepository $eventRepository, EntityManagerInterface $em, WeatherService $weatherService): Response
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

        $eventWeather = null;
        $weatherError = null;
        $governorate = (string) ($event->getGouvernorat() ?? '');
        if ($governorate !== '') {
            try {
                $eventWeather = $weatherService->getCurrentWeatherForGovernorate($governorate);
            } catch (\RuntimeException $exception) {
                $weatherError = $exception->getMessage();
            }
        }

        $hasEventTicket = false;
        $hasUsedEventTicket = false;
        /** @var UserModel|null $user */
        $user = $this->getUser();
        if ($user instanceof UserModel) {
            $hasEventTicket = (int) $em->getRepository(Ticket::class)->count([
                'event' => $event,
                'user' => $user,
            ]) > 0;

            $hasUsedEventTicket = (int) $em->getRepository(Ticket::class)->count([
                'event' => $event,
                'user' => $user,
                'isUsed' => true,
            ]) > 0;
        }

        return $this->render('front/event_show.html.twig', [
            'event' => $event,
            'eventWeather' => $eventWeather,
            'weatherError' => $weatherError,
            'hasTickets' => $this->currentUserHasTickets($em),
            'hasEventTicket' => $hasEventTicket,
            'hasUsedEventTicket' => $hasUsedEventTicket,
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
            $ticket->setQrCode($this->generateUniqueQrToken($em));

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

        $now = new \DateTimeImmutable();
        $upcomingTickets = [];
        $historyTickets = [];

        foreach ($tickets as $ticket) {
            if (!$ticket instanceof Ticket) {
                continue;
            }

            $event = $ticket->getEvent();
            $isPastEvent = $event instanceof Event
                && $event->getEndDate() instanceof \DateTimeInterface
                && $event->getEndDate() < $now;

            if ((bool) $ticket->isUsed() || $isPastEvent) {
                $historyTickets[] = $ticket;
                continue;
            }

            $upcomingTickets[] = $ticket;
        }

        return $this->render('front/my_tickets.html.twig', [
            'tickets' => $tickets,
            'upcomingTickets' => $upcomingTickets,
            'historyTickets' => $historyTickets,
            'now' => $now,
            'hasTickets' => count($tickets) > 0,
        ]);
    }

    #[Route('/my-tickets/{id}/pdf', name: 'app_my_ticket_pdf', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function myTicketPdf(int $id, EntityManagerInterface $em, Pdf $pdf): Response
    {
        /** @var UserModel|null $user */
        $user = $this->getUser();

        if (!$user instanceof UserModel) {
            $this->addFlash('info', 'Connectez-vous pour télécharger vos billets.');

            return $this->redirectToRoute('app_login');
        }

        $ticket = $em->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.event', 'e')
            ->addSelect('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
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

        if (!$ticket->getQrCode()) {
            $ticket->setQrCode($this->generateUniqueQrToken($em));
            $em->flush();
        }

        $appPublicUrl = $_ENV['APP_PUBLIC_URL'] ?? 'http://127.0.0.1:8000';

        $html = $this->renderView('front/ticket_pdf.html.twig', [
            'ticket' => $ticket,
            'appPublicUrl' => $appPublicUrl,
        ]);

        $eventTitle = $ticket->getEvent() instanceof Event ? (string) $ticket->getEvent()->getTitle() : 'event';
        $filename = sprintf('billet-%s-%d.pdf', $this->slugifyFilename($eventTitle), (int) $ticket->getId());

        return new Response(
            $pdf->getOutputFromHtml($html),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
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

    #[Route('/admin/tickets/scan/{token}', name: 'app_ticket_scan_auto', methods: ['GET'])]
    public function scanTicketPreview(string $token, EntityManagerInterface $em): Response
    {
        if (!$this->canCurrentUserScanTickets()) {
            throw new AccessDeniedException('Acces reserve a l administration et aux organisateurs.');
        }

        $token = trim($token);
        if ($token === '') {
            return $this->render('ticket/scan_result.html.twig', [
                'status' => 'invalid',
                'title' => 'QR invalide',
                'message' => 'Le code QR scanne est vide ou invalide.',
                'ticket' => null,
            ]);
        }

        $ticket = $this->findTicketByQrToken($em, $token);

        if (!$ticket instanceof Ticket) {
            return $this->render('ticket/scan_result.html.twig', [
                'status' => 'invalid',
                'title' => 'Billet introuvable',
                'message' => 'Aucun billet ne correspond a ce QR code.',
                'ticket' => null,
            ]);
        }

        if ((bool) $ticket->isUsed()) {
            return $this->render('ticket/scan_result.html.twig', [
                'status' => 'already_used',
                'title' => 'Billet deja utilise',
                'message' => 'Ce billet est deja en statut USED. Entree refusee.',
                'ticket' => $ticket,
                'token' => $token,
            ]);
        }

        if (!$this->isTicketScanDayOpen($ticket)) {
            $event = $ticket->getEvent();
            $startAt = $event instanceof Event ? $event->getStartDate() : null;
            $formattedStartAt = $startAt instanceof \DateTimeInterface ? $startAt->format('d/m/Y H:i') : 'date inconnue';

            return $this->render('ticket/scan_result.html.twig', [
                'status' => 'too_early',
                'title' => 'Événement pas encore commencé',
                'message' => sprintf('Ce billet ne peut pas être validé maintenant. L événement est prévu le %s.', $formattedStartAt),
                'ticket' => $ticket,
                'token' => $token,
            ]);
        }

        return $this->render('ticket/scan_result.html.twig', [
            'status' => 'preview',
            'title' => 'Billet trouve',
            'message' => 'Verifiez visuellement le participant, puis confirmez avec le bouton.',
            'ticket' => $ticket,
            'token' => $token,
        ]);
    }

    #[Route('/admin/tickets/scan/{token}/validate', name: 'app_ticket_scan_validate', methods: ['POST'])]
    public function validateScannedTicket(string $token, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->canCurrentUserScanTickets()) {
            throw new AccessDeniedException('Acces reserve a l administration et aux organisateurs.');
        }

        $token = trim($token);
        if ($token === '') {
            return $this->render('ticket/scan_result.html.twig', [
                'status' => 'invalid',
                'title' => 'QR invalide',
                'message' => 'Le code QR scanne est vide ou invalide.',
                'ticket' => null,
            ]);
        }

        if (!$this->isCsrfTokenValid('scan_validate_' . $token, (string) $request->request->get('_token'))) {
            return $this->render('ticket/scan_result.html.twig', [
                'status' => 'invalid',
                'title' => 'Action invalide',
                'message' => 'Le jeton de securite est invalide. Re-scannez le billet.',
                'ticket' => $this->findTicketByQrToken($em, $token),
                'token' => $token,
            ]);
        }

        $ticket = $this->findTicketByQrToken($em, $token);

        if (!$ticket instanceof Ticket) {
            return $this->render('ticket/scan_result.html.twig', [
                'status' => 'invalid',
                'title' => 'Billet introuvable',
                'message' => 'Aucun billet ne correspond a ce QR code.',
                'ticket' => null,
            ]);
        }

        if (!$this->isTicketScanDayOpen($ticket)) {
            $event = $ticket->getEvent();
            $startAt = $event instanceof Event ? $event->getStartDate() : null;
            $formattedStartAt = $startAt instanceof \DateTimeInterface ? $startAt->format('d/m/Y H:i') : 'date inconnue';

            return $this->render('ticket/scan_result.html.twig', [
                'status' => 'too_early',
                'title' => 'Événement pas encore commencé',
                'message' => sprintf('Ce billet ne peut pas être validé maintenant. L événement est prévu le %s.', $formattedStartAt),
                'ticket' => $ticket,
                'token' => $token,
            ]);
        }

        // Atomic transition VALID -> USED to prevent race conditions on double validation.
        $updatedRows = $em->createQuery(
            'UPDATE App\\Entity\\Event\\Ticket t
             SET t.isUsed = true, t.usedAt = :usedAt
             WHERE t.qrCode = :token AND t.isUsed = false'
        )
            ->setParameter('usedAt', new \DateTime())
            ->setParameter('token', $token)
            ->execute();

        if ($updatedRows > 0) {
            $em->refresh($ticket);

            return $this->render('ticket/scan_result.html.twig', [
                'status' => 'success',
                'title' => 'Entree validee',
                'message' => 'Le billet est maintenant en statut USED. Participant autorise.',
                'ticket' => $ticket,
                'token' => $token,
            ]);
        }

        return $this->render('ticket/scan_result.html.twig', [
            'status' => 'already_used',
            'title' => 'Billet deja utilise',
            'message' => 'Ce billet a deja ete valide auparavant. Entree refusee.',
            'ticket' => $ticket,
            'token' => $token,
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

    private function generateUniqueQrToken(EntityManagerInterface $em): string
    {
        do {
            $token = 'tkt_' . bin2hex(random_bytes(24));
            $exists = (int) $em->getRepository(Ticket::class)->count(['qrCode' => $token]) > 0;
        } while ($exists);

        return $token;
    }

    private function canCurrentUserScanTickets(): bool
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_ORGANISATEUR')) {
            return true;
        }

        /** @var UserModel|null $user */
        $user = $this->getUser();

        if (!$user instanceof UserModel) {
            return false;
        }

        return in_array((int) $user->getRoleId(), [2, 3], true);
    }

    private function findTicketByQrToken(EntityManagerInterface $em, string $token): ?Ticket
    {
        $ticket = $em->getRepository(Ticket::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.event', 'e')
            ->addSelect('e')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->andWhere('t.qrCode = :token')
            ->setParameter('token', $token)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $ticket instanceof Ticket ? $ticket : null;
    }

    private function isTicketScanDayOpen(Ticket $ticket): bool
    {
        $event = $ticket->getEvent();

        if (!$event instanceof Event) {
            return false;
        }

        $startAt = $event->getStartDate();

        if (!$startAt instanceof \DateTimeInterface) {
            return false;
        }

        return $startAt->format('Y-m-d') === (new \DateTimeImmutable('today'))->format('Y-m-d');
    }

    private function slugifyFilename(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value) ?? 'ticket';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'ticket';
    }
}