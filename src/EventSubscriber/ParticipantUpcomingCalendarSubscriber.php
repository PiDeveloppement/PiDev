<?php

namespace App\EventSubscriber;

use App\Entity\Event\Event;
use App\Repository\Event\EventRepository;
use CalendarBundle\CalendarEvents;
use CalendarBundle\Entity\Event as CalendarEntry;
use CalendarBundle\Event\CalendarEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ParticipantUpcomingCalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CalendarEvents::SET_DATA => 'onCalendarSetData',
        ];
    }

    public function onCalendarSetData(CalendarEvent $calendar): void
    {
        $filters = $calendar->getFilters();
        $scope = $filters['scope'] ?? null;

        if ($scope === null && isset($filters['filters']) && is_string($filters['filters'])) {
            $decoded = json_decode($filters['filters'], true);
            if (is_array($decoded)) {
                $scope = $decoded['scope'] ?? null;
            }
        }

        if ($scope !== 'participant_upcoming') {
            return;
        }

        $start = $calendar->getStart();
        $end = $calendar->getEnd();
        $now = new \DateTimeImmutable();

        $events = $this->eventRepository->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->andWhere('e.status = :status')
            ->andWhere('e.endDate >= :now')
            ->andWhere('(e.startDate <= :end AND e.endDate >= :start)')
            ->setParameter('status', Event::STATUS_PUBLISHED)
            ->setParameter('now', $now)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($events as $event) {
            if (!$event instanceof Event || !$event->getStartDate() instanceof \DateTimeInterface) {
                continue;
            }

            $categoryName = $event->getCategory() ? (string) $event->getCategory()->getName() : 'Sans catégorie';
            // Utiliser la couleur de la catégorie (définie dans le backoffice), ou couleur par défaut
            $backgroundColor = $event->getCategory() && $event->getCategory()->getColor() 
                ? (string) $event->getCategory()->getColor() 
                : '#9E9E9E';

            $calendar->addEvent(new CalendarEntry(
                (string) $event->getTitle(),
                $event->getStartDate(),
                $event->getEndDate(),
                null,
                [
                    'url' => $this->urlGenerator->generate('app_public_event_show', ['id' => $event->getId()]),
                    'backgroundColor' => $backgroundColor,
                    'borderColor' => $backgroundColor,
                    'textColor' => '#ffffff',
                    'classNames' => ['event-category-item'],
                    'extendedProps' => [
                        'location' => $event->getLocation(),
                        'category' => $categoryName,
                        'isFree' => (bool) $event->isIsFree(),
                        'ticketPrice' => (float) ($event->getTicketPrice() ?? 0),
                    ],
                ]
            ));
        }
    }
}
