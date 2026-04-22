<?php

namespace StatisticsBundle\Service;

use App\Repository\User\UserRepository;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

class StatisticsService
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private EventRepository $eventRepository;
    private TicketRepository $ticketRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        EventRepository $eventRepository,
        TicketRepository $ticketRepository
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->eventRepository = $eventRepository;
        $this->ticketRepository = $ticketRepository;
    }

    /**
     * Calcule le taux de participation global (participants / utilisateurs totaux)
     */
    public function getParticipationRate(): array
    {
        $totalUsers = $this->userRepository->countAll();
        
        // Compter les participants uniques (utilisateurs avec au moins un ticket)
        $uniqueParticipants = $this->ticketRepository->createQueryBuilder('t')
            ->select('COUNT(DISTINCT t.userId)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $participationRate = $totalUsers > 0 ? round(($uniqueParticipants / $totalUsers) * 100, 2) : 0;

        return [
            'total_users' => $totalUsers,
            'unique_participants' => (int) $uniqueParticipants,
            'participation_rate' => $participationRate,
            'non_participants' => $totalUsers - (int) $uniqueParticipants,
        ];
    }

    /**
     * Calcule le taux de participation par événement
     */
    public function getParticipationByEvent(): array
    {
        $events = $this->eventRepository->findAll();
        $eventStats = [];

        foreach ($events as $event) {
            $capacity = $event->getCapacity() ?? 0;
            $participants = $this->ticketRepository->createQueryBuilder('t')
                ->select('COUNT(DISTINCT t.userId)')
                ->where('t.eventId = :eventId')
                ->setParameter('eventId', $event->getId())
                ->getQuery()
                ->getSingleScalarResult();

            $rate = $capacity > 0 ? round(($participants / $capacity) * 100, 2) : 0;

            $eventStats[] = [
                'event_id' => $event->getId(),
                'event_title' => $event->getTitle(),
                'capacity' => $capacity,
                'participants' => (int) $participants,
                'participation_rate' => $rate,
                'available_places' => max(0, $capacity - (int) $participants),
            ];
        }

        return $eventStats;
    }

    /**
     * Obtient toutes les statistiques globales
     */
    public function getAllStatistics(): array
    {
        return [
            'participation' => $this->getParticipationRate(),
            'events_participation' => $this->getParticipationByEvent(),
        ];
    }
}
