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
            6,
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

    public function getCalendarEvents(?string $googleApiKey = null, ?string $googleCalendarId = null): array
    {
        $events = $this->buildCalendarEvents($this->eventRepository->findAllOrderedByDate());
        $events = array_merge($events, $this->fetchGoogleCalendarEvents($googleApiKey, $googleCalendarId));

        usort($events, static function (array $left, array $right): int {
            return strcmp((string) ($left['start'] ?? ''), (string) ($right['start'] ?? ''));
        });

        return $events;
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

    private function buildCalendarEvents(array $events): array
    {
        $now = new \DateTimeImmutable();
        $result = [];

        foreach ($events as $event) {
            if (!$event instanceof Event || !$event->getStartDate() || !$event->getEndDate()) {
                continue;
            }

            $status = 'À venir';
            $backgroundColor = '#2563eb';

            if ($event->getEndDate() < $now) {
                $status = 'Terminé';
                $backgroundColor = '#64748b';
            } elseif ($event->getStartDate() <= $now && $event->getEndDate() >= $now) {
                $status = 'En cours';
                $backgroundColor = '#16a34a';
            }

            $result[] = [
                'id' => $event->getId(),
                'title' => (string) ($event->getTitle() ?? 'Événement'),
                'start' => $event->getStartDate()->format('Y-m-d\\TH:i:s'),
                'end' => $event->getEndDate()->format('Y-m-d\\TH:i:s'),
                'location' => (string) ($event->getLocation() ?? '-'),
                'status' => $status,
                'backgroundColor' => $backgroundColor,
                'borderColor' => $backgroundColor,
                'textColor' => '#ffffff',
            ];
        }

        return $result;
    }

    private function fetchGoogleCalendarEvents(?string $googleApiKey, ?string $googleCalendarId): array
    {
        if (!$googleApiKey || !$googleCalendarId) {
            return [];
        }

        try {
            $timeMin = (new \DateTimeImmutable('-1 year'))->format(DATE_ATOM);
            $timeMax = (new \DateTimeImmutable('+1 year'))->format(DATE_ATOM);
            $endpoint = sprintf(
                'https://www.googleapis.com/calendar/v3/calendars/%s/events?key=%s&singleEvents=true&orderBy=startTime&timeMin=%s&timeMax=%s&maxResults=50',
                rawurlencode($googleCalendarId),
                rawurlencode($googleApiKey),
                rawurlencode($timeMin),
                rawurlencode($timeMax)
            );

            $payload = $this->fetchJson($endpoint);
            $items = $payload['items'] ?? [];
            $result = [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $start = $item['start']['dateTime'] ?? $item['start']['date'] ?? null;
                $end = $item['end']['dateTime'] ?? $item['end']['date'] ?? null;

                if (!$start) {
                    continue;
                }

                $result[] = [
                    'id' => 'google-' . substr(sha1((string) ($item['id'] ?? $start . ($item['summary'] ?? 'google'))), 0, 12),
                    'title' => (string) ($item['summary'] ?? 'Google Calendar'),
                    'start' => $start,
                    'end' => $end ?: $start,
                    'location' => (string) ($item['location'] ?? '-'),
                    'status' => 'Google Calendar',
                    'backgroundColor' => '#0ea5e9',
                    'borderColor' => '#0ea5e9',
                    'textColor' => '#ffffff',
                    'sourceType' => 'google',
                    'url' => $item['htmlLink'] ?? null,
                ];
            }

            return $result;
        } catch (\Throwable $exception) {
            return [];
        }
    }

    private function fetchJson(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException('Google Calendar API request failed.');
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Google Calendar API returned invalid JSON.');
        }

        return $decoded;
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
