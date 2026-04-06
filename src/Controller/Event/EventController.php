<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Entity\User\UserModel;
use App\Form\Event\EventType;
use App\Service\Event\EventService;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Snappy\Pdf;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/event')]
class EventController extends AbstractController
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private EventService $eventService,
        #[Autowire('%env(default::GOOGLE_API_KEY)%')] private readonly ?string $googleApiKey = null,
        #[Autowire('%env(default::GOOGLE_CALENDAR_ID)%')] private readonly ?string $googleCalendarId = null
    ) {}

    #[Route('/', name: 'app_event_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $listData = $this->eventService->getBackOfficeListData(
            $request->query->getInt('page', 1),
            $paginator
        );

        return $this->render('event/index.html.twig', [
            'events' => $listData['events'],
            'categories' => $listData['categories'],
            'totalEvents' => $listData['totalEvents'],
            'totalTickets' => $listData['totalTickets'],
            'totalAvenir' => $listData['totalAvenir'],
            'totalTermine' => $listData['totalTermine'],
            'pageInfo' => [
                'title' => 'Gestion des événements',
                'subtitle' => 'Consultez et gérez tous vos événements',
            ],
        ]);
    }

    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if (!$this->isGranted('ROLE_ORGANISATEUR') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Seuls les organisateurs peuvent creer des evenements.');
        }

        $event = new Event();
        $categories = $this->eventService->getCategoriesForSelect();
        $form = $this->formFactory->createNamed('', EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $category = $this->eventService->resolveCategoryById((int) $event->getCategoryId());
            if (!$category) {
                $form->get('categoryId')->addError(new FormError('La categorie selectionnee est invalide.'));
            }

            $priceError = $this->eventService->validatePaidEventPrice($event);
            if ($priceError !== null) {
                $form->get('ticketPrice')->addError(new FormError($priceError));
            }

            if ($form->isValid()) {
                /** @var UserModel|null $currentUser */
                $currentUser = $this->getUser();
                if ($currentUser instanceof UserModel) {
                    $event->setCreator($currentUser);
                    $event->setCreatedBy($currentUser->getId());

                    if ($this->isGranted('ROLE_ORGANISATEUR')) {
                        $event->setStatus(Event::STATUS_PUBLISHED);
                    }
                }

                $this->eventService->createEvent($event, $category);

                return $this->redirectToRoute('app_event_index');
            }
        }

        return $this->render('event/new.html.twig', [
            'event' => $event,
            'categories' => $categories,
            'pageInfo' => [
                'title' => 'Nouvel Événement',
                'subtitle' => 'Créez un nouvel événement',
            ],
        ]);
    }

    #[Route('/calendar', name: 'app_event_calendar', methods: ['GET'])]
    public function calendar(): Response
    {
        return $this->render('event/calendar.html.twig', [
            'calendarEvents' => $this->eventService->getCalendarEvents($this->googleApiKey, $this->googleCalendarId),
            'pageInfo' => [
                'title' => 'Calendrier des événements',
                'subtitle' => 'Vue interactive des événements',
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_event_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
            'pageInfo' => [
                'title' => "Détails de l'Événement",
                'subtitle' => "Consultation des informations de l'événement",
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(Event $event, Request $request): Response
    {
        $categories = $this->eventService->getCategoriesForSelect();
        $form = $this->formFactory->createNamed('', EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $category = $this->eventService->resolveCategoryById((int) $event->getCategoryId());
            if (!$category) {
                $form->get('categoryId')->addError(new FormError('La categorie selectionnee est invalide.'));
            }

            $priceError = $this->eventService->validatePaidEventPrice($event);
            if ($priceError !== null) {
                $form->get('ticketPrice')->addError(new FormError($priceError));
            }

            if ($form->isValid()) {
                $this->eventService->updateEvent($event, $category);

                return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
            }
        }

        return $this->render('event/new.html.twig', [
            'event' => $event,
            'currentEvent' => $event,
            'categories' => $categories,
            'pageInfo' => [
                'title' => 'Modifier Événement',
                'subtitle' => 'Mettez à jour les informations de l\'événement',
            ],
        ]);
    }

    #[Route('/export-pdf', name: 'app_event_export_pdf', methods: ['GET'])]
    public function exportPdf(Pdf $pdf): Response
    {
        $events = $this->eventService->getEventsForExport();
        $html = $this->renderView('event/pdf.html.twig', [
            'events' => $events,
        ]);

        $filename = 'events_' . date('Y-m-d') . '.pdf';

        return new Response(
            $pdf->getOutputFromHtml($html),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    #[Route('/{id}/delete', name: 'app_event_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Event $event): Response
    {
        $this->eventService->deleteEvent($event);

        return $this->redirectToRoute('app_event_index');
    }
}
