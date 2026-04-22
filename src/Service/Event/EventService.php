<?php

namespace App\Service\Event;

use App\Entity\Event\Category;
use App\Entity\Event\Event;
use App\Repository\Event\CategoryRepository;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TicketRepository;
use App\Service\Event\GoogleCalendarWriteService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

class EventService
{
    public function __construct(
        private EventRepository $eventRepository,
        private CategoryRepository $categoryRepository,
        private TicketRepository $ticketRepository,
        private EntityManagerInterface $entityManager,
        private GoogleCalendarWriteService $googleCalendarWriteService
    ) {
    }

    public function getBackOfficeListData(int $page, PaginatorInterface $paginator, array $filters = []): array
    {
        $filteredQueryBuilder = $this->eventRepository->createBackOfficeListQueryBuilder($filters);

        $events = $paginator->paginate(
            $filteredQueryBuilder,
            $page,
            6,
            ['wrap-queries' => true]
        );

        /** @var Event[] $filteredEvents */
        $filteredEvents = (clone $filteredQueryBuilder)->getQuery()->getResult();

        $now = new \DateTimeImmutable();
        $totalAvenir = 0;
        $totalTermine = 0;
        $totalTickets = 0;

        foreach ($filteredEvents as $event) {
            if ($event->getStartDate() && $event->getStartDate() > $now) {
                $totalAvenir++;
            }
            if ($event->getEndDate() && $event->getEndDate() < $now) {
                $totalTermine++;
            }

            $totalTickets += $event->getTickets()->count();
        }

        return [
            'events' => $events,
            'categories' => $this->categoryRepository->findAllOrderedByName(),
            'totalEvents' => count($filteredEvents),
            'filteredEvents' => $events->getTotalItemCount(),
            'totalTickets' => $totalTickets,
            'totalAvenir' => $totalAvenir,
            'totalTermine' => $totalTermine,
            'filters' => $filters,
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

    public function createEvent(Event $event, ?Category $category): bool
    {
        $this->prepareEventBeforeSave($event, $category, true);
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->googleCalendarWriteService->syncCreatedEvent($event);
    }

    public function getGoogleCalendarSyncError(): ?string
    {
        return $this->googleCalendarWriteService->getLastError();
    }

    public function updateEvent(Event $event, ?Category $category): bool
    {
        $this->prepareEventBeforeSave($event, $category, false);
        $this->entityManager->flush();

        return $this->googleCalendarWriteService->syncUpdatedEvent($event);
    }

    public function deleteEvent(Event $event): bool
    {
        $googleSyncOk = $this->googleCalendarWriteService->syncDeletedEvent($event);
        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return $googleSyncOk;
    }

    /**
     * Pull changes from Google Calendar for linked EventFlow events.
     *
     * @return array{updated:int,deleted:int,failed:int,skipped:int}
     */
    public function syncFromGoogleCalendarToEventFlow(): array
    {
        $stats = [
            'updated' => 0,
            'deleted' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $hasChanges = false;
        $events = $this->eventRepository->findAllOrderedByDate();

        foreach ($events as $event) {
            if (!$event instanceof Event || !$event->getId()) {
                continue;
            }

            $remoteResult = $this->googleCalendarWriteService->fetchRemoteEventByLocalId($event->getId(), $event);

            if (!(bool) ($remoteResult['ok'] ?? false)) {
                $stats['failed']++;
                continue;
            }

            if (!(bool) ($remoteResult['found'] ?? false)) {
                // Keep local event if it still has dependent records (tickets/questions)
                // to avoid FK violations during automatic Google -> EventFlow sync.
                if ($event->getTickets()->count() > 0 || $event->getQuestions()->count() > 0) {
                    $stats['skipped']++;
                    continue;
                }

                $this->entityManager->remove($event);
                $stats['deleted']++;
                $hasChanges = true;
                continue;
            }

            $remoteEvent = is_array($remoteResult['event'] ?? null) ? $remoteResult['event'] : [];

            if ($this->applyRemoteChangesToEvent($event, $remoteEvent)) {
                $stats['updated']++;
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->entityManager->flush();
        }

        return $stats;
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

    private function applyRemoteChangesToEvent(Event $event, array $remoteEvent): bool
    {
        $changed = false;

        $remoteTitle = trim((string) ($remoteEvent['title'] ?? ''));
        if ($remoteTitle !== '' && $remoteTitle !== (string) $event->getTitle()) {
            $event->setTitle($remoteTitle);
            $changed = true;
        }

        $remoteDescription = (string) ($remoteEvent['description'] ?? '');
        $normalizedDescription = $remoteDescription === '' ? '-' : $remoteDescription;
        if ($normalizedDescription !== (string) $event->getDescription()) {
            $event->setDescription($normalizedDescription);
            $changed = true;
        }

        $remoteLocation = (string) ($remoteEvent['location'] ?? '');
        if ($remoteLocation !== '' && $remoteLocation !== (string) $event->getLocation()) {
            $event->setLocation($remoteLocation);
            $changed = true;
        }

        $remoteStartRaw = (string) ($remoteEvent['start'] ?? '');
        if ($remoteStartRaw !== '') {
            $remoteStart = new \DateTimeImmutable($remoteStartRaw);
            $currentStart = $event->getStartDate();

            if (!$currentStart || $currentStart->format(DATE_ATOM) !== $remoteStart->format(DATE_ATOM)) {
                $event->setStartDate(\DateTime::createFromImmutable($remoteStart));
                $changed = true;
            }
        }

        $remoteEndRaw = (string) ($remoteEvent['end'] ?? '');
        if ($remoteEndRaw !== '') {
            $remoteEnd = new \DateTimeImmutable($remoteEndRaw);
            $currentEnd = $event->getEndDate();

            if (!$currentEnd || $currentEnd->format(DATE_ATOM) !== $remoteEnd->format(DATE_ATOM)) {
                $event->setEndDate(\DateTime::createFromImmutable($remoteEnd));
                $changed = true;
            }
        }

        if ($changed) {
            $event->setUpdatedAt(new \DateTime());
        }

        return $changed;
    }
}