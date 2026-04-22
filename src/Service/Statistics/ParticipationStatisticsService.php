<?php

namespace App\Service\Statistics;

use App\Repository\User\UserRepository;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

class ParticipationStatisticsService
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
     * Obtient l'historique des taux de participation sur les 6 derniers mois
     */
    public function getParticipationHistory(): array
    {
        $history = [];
        $months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = (new \DateTime())->modify("-$i months");
            $monthLabel = $months[$i];
            
            // Simuler des données historiques (à remplacer par des requêtes réelles)
            $totalUsers = $this->userRepository->countAll();
            $uniqueParticipants = $this->ticketRepository->createQueryBuilder('t')
                ->select('COUNT(DISTINCT t.userId)')
                ->where('t.createdAt >= :startMonth')
                ->andWhere('t.createdAt <= :endMonth')
                ->setParameter('startMonth', $date->modify('first day of this month')->format('Y-m-d 00:00:00'))
                ->setParameter('endMonth', $date->modify('last day of this month')->format('Y-m-d 23:59:59'))
                ->getQuery()
                ->getSingleScalarResult();
            
            $rate = $totalUsers > 0 ? round(($uniqueParticipants / $totalUsers) * 100, 2) : 0;
            
            $history[] = [
                'month' => $monthLabel,
                'rate' => $rate,
                'participants' => (int) $uniqueParticipants,
            ];
        }
        
        return $history;
    }

    /**
     * Obtient toutes les statistiques globales
     */
    public function getAllStatistics(): array
    {
        return [
            'participation' => $this->getParticipationRate(),
            'events_participation' => $this->getParticipationByEvent(),
            'participation_history' => $this->getParticipationHistory(),
        ];
    }
}
