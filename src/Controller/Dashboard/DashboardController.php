<?php

namespace App\Controller\Dashboard;

use App\Entity\Event\Event;
use App\Entity\User\UserModel;
use App\Service\User\UserService;  // Ajoutez cette ligne
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/dashboard')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DashboardController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private \DateTime $lastUpdate;
    private UserService $userService;  // Ajoutez cette ligne

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        UserService $userService  // Ajoutez ce paramètre
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->userService = $userService;  // Ajoutez cette ligne
        $this->lastUpdate = new \DateTime();
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $this->logger->info('Initialisation DashboardController...');

        try {
            $stats = $this->loadDashboardData();
            
            // Afficher les statistiques dans les logs pour déboguer
            $this->logger->info('📊 Statistiques calculées:', [
                'total_events' => $stats['total_events'],
                'planned' => $stats['planned'],
                'ongoing' => $stats['ongoing'],
                'completed' => $stats['completed'],
                'planned_percent' => $stats['planned_percent'],
                'ongoing_percent' => $stats['ongoing_percent'],
                'completed_percent' => $stats['completed_percent'],
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur chargement données: ' . $e->getMessage());
            $stats = $this->getErrorStats();
        }

        return $this->render('dashboard/dashboard.html.twig', [
            'stats' => $stats,
            'upcoming_events' => $stats['upcoming_events'] ?? []
        ]);
    }

    #[Route('/refresh', name: 'app_dashboard_refresh', methods: ['GET'])]
    public function refresh(): JsonResponse
    {
        try {
            $stats = $this->loadDashboardData();
            
            return $this->json([
                'totalEvents' => $stats['total_events'],
                'totalParticipants' => $stats['total_users'],
                'totalTickets' => $stats['total_tickets'],
                'participationRate' => $stats['participation_rate'],
                'eventsEvolution' => $stats['events_evolution'],
                'participantsEvolution' => $stats['participants_evolution'],
                'participationEvolution' => $stats['participation_evolution'],
                'ticketsEvolution' => $stats['tickets_evolution'],
                'planifiePercent' => $stats['planned_percent'],
                'planifieCount' => $stats['planned'],
                'enCoursPercent' => $stats['ongoing_percent'],
                'enCoursCount' => $stats['ongoing'],
                'terminePercent' => $stats['completed_percent'],
                'termineCount' => $stats['completed'],
                // Ajout des stats utilisateurs pour le refresh AJAX
                'totalUsers' => $stats['total_users_stats'] ?? 0,
                'newUsersThisMonth' => $stats['new_users_this_month'] ?? 0,
                'usersEvolution' => $stats['users_evolution'] ?? 0,
                'adminsPercent' => $stats['admins_percent'] ?? 0,
                'adminsCount' => $stats['admins_count'] ?? 0,
                'organizersPercent' => $stats['organizers_percent'] ?? 0,
                'organizersCount' => $stats['organizers_count'] ?? 0,
                'defaultPercent' => $stats['default_percent'] ?? 0,
                'defaultCount' => $stats['default_count'] ?? 0,
                'sponsorsPercent' => $stats['sponsors_percent'] ?? 0,
                'sponsorsCount' => $stats['sponsors_count'] ?? 0,
                'lastUpdate' => $this->lastUpdate->format('H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur refresh dashboard: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function loadDashboardData(): array
    {
        // Récupérer les repositories
        $eventRepository = $this->entityManager->getRepository(Event::class);
        $userRepository = $this->entityManager->getRepository(UserModel::class);
        $ticketRepository = $this->entityManager->getRepository('App\Entity\Event\Ticket');
        
        $this->logger->info('🔍 Repositories chargés');
        
        // ==================== STATS ÉVÉNEMENTS ====================
        // Compter les événements
        $totalEvents = $eventRepository->count([]);
        $this->logger->info('📊 Total événements: ' . $totalEvents);
        
        // Récupérer TOUS les événements pour les statistiques
        $allEvents = $eventRepository->findAll();
        $this->logger->info('📊 Nombre événements trouvés: ' . count($allEvents));
        
        // Compter les billets
        $totalTickets = $ticketRepository->count([]);
        $this->logger->info('📊 Total billets: ' . $totalTickets);
        
        // Récupérer les événements à venir
        $upcomingEvents = $eventRepository->createQueryBuilder('e')
            ->where('e.startDate > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.startDate', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        // Calculer les statistiques des événements
        $eventStats = $this->calculateEventStats($allEvents);
        $this->logger->info('📊 Stats événements:', $eventStats);
        
        // Calculer le nombre de participants uniques
        $totalParticipants = $this->countUniqueParticipants($ticketRepository);
        $this->logger->info('📊 Total participants uniques: ' . $totalParticipants);
        
        // Calculer le taux de participation
        $participationRate = $this->calculateAverageParticipationRate($allEvents, $totalParticipants);
        
        // ==================== STATS UTILISATEURS ====================
        // Total utilisateurs
        $totalUsers = $userRepository->count([]);
        $this->logger->info('📊 Total utilisateurs: ' . $totalUsers);
        
        // Utilisateurs ce mois
        $now = new \DateTime();
        $monthAgo = (clone $now)->modify('-1 month');
        $newUsersThisMonth = $userRepository->createQueryBuilder('u')
            ->where('u.registrationDate >= :start')
            ->setParameter('start', $monthAgo)
            ->getQuery()
            ->getResult();
        $newUsersCount = count($newUsersThisMonth);
        $this->logger->info('📊 Nouveaux utilisateurs ce mois: ' . $newUsersCount);
        
        // Utilisateurs par rôle - utiliser RoleId directement
        $adminCount = $userRepository->count(['roleId' => 4]);
        $organizerCount = $userRepository->count(['roleId' => 2]);
        $sponsorCount = $userRepository->count(['roleId' => 3]);
        $defaultCount = $totalUsers - $adminCount - $organizerCount - $sponsorCount;
        
        $this->logger->info('📊 Admins: ' . $adminCount . ', Org: ' . $organizerCount . ', Sponsor: ' . $sponsorCount);
        
        $userStats = [
            'total_users_stats' => $totalUsers,
            'new_users_this_month' => $newUsersCount,
            'admins_count' => $adminCount,
            'organizers_count' => $organizerCount,
            'default_count' => $defaultCount,
            'sponsors_count' => $sponsorCount,
            'admins_percent' => ($totalUsers > 0) ? round(($adminCount / $totalUsers) * 100) : 0,
            'organizers_percent' => ($totalUsers > 0) ? round(($organizerCount / $totalUsers) * 100) : 0,
            'default_percent' => ($totalUsers > 0) ? round(($defaultCount / $totalUsers) * 100) : 0,
            'sponsors_percent' => ($totalUsers > 0) ? round(($sponsorCount / $totalUsers) * 100) : 0,
        ];
        
        // ==================== ÉVOLUTIONS ====================
        $eventsEvolution = $this->calculateEventsEvolution($eventRepository);
        $participantsEvolution = $this->calculateParticipantsEvolution($totalUsers);
        $participationEvolution = $this->calculateParticipationEvolution($participationRate);
        $ticketsEvolution = $this->calculateTicketsEvolution($ticketRepository);
        $usersEvolution = $this->calculateUsersEvolution($userRepository);

        $this->lastUpdate = new \DateTime();

        // Fusionner toutes les statistiques
        return array_merge([
            'total_events' => $totalEvents,
            'total_users' => $totalParticipants,
            'total_tickets' => $totalTickets,
            'participation_rate' => round($participationRate, 1),
            'events_evolution' => $eventsEvolution,
            'participants_evolution' => $participantsEvolution,
            'participation_evolution' => $participationEvolution,
            'tickets_evolution' => $ticketsEvolution,
            'users_evolution' => $usersEvolution,
            'planned' => $eventStats['planned'],
            'ongoing' => $eventStats['ongoing'],
            'completed' => $eventStats['completed'],
            'planned_percent' => $eventStats['total'] > 0 ? round(($eventStats['planned'] / $eventStats['total']) * 100) : 0,
            'ongoing_percent' => $eventStats['total'] > 0 ? round(($eventStats['ongoing'] / $eventStats['total']) * 100) : 0,
            'completed_percent' => $eventStats['total'] > 0 ? round(($eventStats['completed'] / $eventStats['total']) * 100) : 0,
            'upcoming_events' => array_map([$this, 'formatEventForDisplay'], $upcomingEvents),
            'total' => $eventStats['total']
        ], $userStats);
    }

    // ==================== NOUVELLE MÉTHODE ====================
    private function calculateUsersEvolution($userRepository): int
    {
        try {
            $now = new \DateTime();
            $monthAgo = (clone $now)->modify('-1 month');
            $twoMonthsAgo = (clone $now)->modify('-2 months');

            $usersThisMonth = $userRepository->createQueryBuilder('u')
                ->where('u.registrationDate BETWEEN :start AND :end')
                ->setParameter('start', $monthAgo)
                ->setParameter('end', $now)
                ->getQuery()
                ->getResult();

            $usersLastMonth = $userRepository->createQueryBuilder('u')
                ->where('u.registrationDate BETWEEN :start AND :end')
                ->setParameter('start', $twoMonthsAgo)
                ->setParameter('end', $monthAgo)
                ->getQuery()
                ->getResult();

            $countThisMonth = count($usersThisMonth);
            $countLastMonth = count($usersLastMonth);

            if ($countLastMonth === 0) {
                return $countThisMonth > 0 ? 100 : 0;
            }

            return round((($countThisMonth - $countLastMonth) / $countLastMonth) * 100);
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur calcul évolution utilisateurs: ' . $e->getMessage());
            return 0;
        }
    }

    // ==================== MÉTHODES EXISTANTES (gardez-les) ====================
    
    private function calculateEventStats(array $events): array
    {
        $planned = 0;
        $ongoing = 0;
        $completed = 0;
        $now = new \DateTime();

        foreach ($events as $event) {
            $startDate = $event->getStartDate();
            $endDate = $event->getEndDate();

            if ($endDate && $endDate < $now) {
                $completed++;
            } elseif ($startDate && $startDate <= $now && $endDate && $endDate >= $now) {
                $ongoing++;
            } elseif ($startDate && $startDate > $now) {
                $planned++;
            } else {
                $planned++;
            }
        }

        return [
            'planned' => $planned,
            'ongoing' => $ongoing,
            'completed' => $completed,
            'total' => count($events)
        ];
    }

    private function countUniqueParticipants($ticketRepository): int
    {
        if (!$ticketRepository) {
            return 0;
        }
        
        try {
            $qb = $ticketRepository->createQueryBuilder('t')
                ->select('COUNT(DISTINCT t.userId) as unique_participants')
                ->getQuery()
                ->getSingleScalarResult();
            
            return (int) ($qb ?? 0);
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur comptage participants: ' . $e->getMessage());
            return 0;
        }
    }

    private function calculateAverageParticipationRate(array $events, int $totalParticipants): float
    {
        if (empty($events)) return 0;

        $totalCapacity = 0;
        foreach ($events as $event) {
            $totalCapacity += $event->getCapacity() ?? 0;
        }

        return $totalCapacity > 0 ? ($totalParticipants / $totalCapacity) * 100 : 0;
    }

    private function calculateEventsEvolution($eventRepository): int
    {
        try {
            $now = new \DateTime();
            $monthAgo = (clone $now)->modify('-1 month');
            $twoMonthsAgo = (clone $now)->modify('-2 months');

            $eventsThisMonth = $eventRepository->createQueryBuilder('e')
                ->where('e.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $monthAgo)
                ->setParameter('end', $now)
                ->getQuery()
                ->getResult();

            $eventsLastMonth = $eventRepository->createQueryBuilder('e')
                ->where('e.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $twoMonthsAgo)
                ->setParameter('end', $monthAgo)
                ->getQuery()
                ->getResult();

            $countThisMonth = count($eventsThisMonth);
            $countLastMonth = count($eventsLastMonth);

            if ($countLastMonth === 0) {
                return $countThisMonth > 0 ? 100 : 0;
            }

            return round((($countThisMonth - $countLastMonth) / $countLastMonth) * 100);
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur calcul évolution événements: ' . $e->getMessage());
            return 0;
        }
    }

    private function calculateParticipantsEvolution($totalUsers): int
    {
        try {
            $now = new \DateTime();
            $monthAgo = (clone $now)->modify('-1 month');
            
            $userRepository = $this->entityManager->getRepository(UserModel::class);
            $usersThisMonth = $userRepository->createQueryBuilder('u')
                ->where('u.registrationDate >= :start')
                ->setParameter('start', $monthAgo)
                ->getQuery()
                ->getResult();

            $countThisMonth = count($usersThisMonth);

            if ($countThisMonth === 0) {
                return 0;
            }

            return round(($countThisMonth / max(1, $totalUsers)) * 100);
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur calcul évolution participants: ' . $e->getMessage());
            return 0;
        }
    }

    private function calculateParticipationEvolution($participationRate): int
    {
        try {
            return min(100, max(0, (int)$participationRate));
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur calcul évolution participation: ' . $e->getMessage());
            return 0;
        }
    }

    private function calculateTicketsEvolution($ticketRepository): int
    {
        if (!$ticketRepository) {
            return 0;
        }
        
        try {
            $now = new \DateTime();
            $monthAgo = (clone $now)->modify('-1 month');
            $twoMonthsAgo = (clone $now)->modify('-2 months');

            $ticketsThisMonth = $ticketRepository->createQueryBuilder('t')
                ->where('t.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $monthAgo)
                ->setParameter('end', $now)
                ->getQuery()
                ->getResult();

            $ticketsLastMonth = $ticketRepository->createQueryBuilder('t')
                ->where('t.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $twoMonthsAgo)
                ->setParameter('end', $monthAgo)
                ->getQuery()
                ->getResult();

            $countThisMonth = count($ticketsThisMonth);
            $countLastMonth = count($ticketsLastMonth);

            if ($countLastMonth === 0) {
                return $countThisMonth > 0 ? 100 : 0;
            }

            return round((($countThisMonth - $countLastMonth) / $countLastMonth) * 100);
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur calcul évolution tickets: ' . $e->getMessage());
            return 0;
        }
    }

    private function formatEventForDisplay($event): array
    {
        $participantsCount = 0;
        
        return [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'startDate' => $event->getStartDate(),
            'location' => $event->getLocation(),
            'capacity' => $event->getCapacity(),
            'participants' => $participantsCount,
            'statusDisplay' => $this->getEventStatus($event),
            'statusColor' => $this->getEventColor($event),
            'statusBg' => $this->getEventColor($event) . '20'
        ];
    }

    private function getEventStatus($event): string
    {
        $now = new \DateTime();
        $startDate = $event->getStartDate();
        $endDate = $event->getEndDate();

        if ($endDate && $endDate < $now) {
            return 'Terminé';
        } elseif ($startDate && $startDate <= $now && $endDate && $endDate >= $now) {
            return 'En cours';
        } elseif ($startDate && $startDate > $now) {
            return 'À venir';
        }
        return 'Planifié';
    }

    private function getEventColor($event): string
    {
        $now = new \DateTime();
        $startDate = $event->getStartDate();
        $endDate = $event->getEndDate();

        if ($endDate && $endDate < $now) {
            return '#64748b';
        } elseif ($startDate && $startDate <= $now && $endDate && $endDate >= $now) {
            return '#3b82f6';
        } elseif ($startDate && $startDate > $now) {
            return '#22c55e';
        }
        return '#f59e0b';
    }

    private function getErrorStats(): array
    {
        return [
            'total_events' => 0,
            'total_users' => 0,
            'total_tickets' => 0,
            'participation_rate' => 0,
            'events_evolution' => 0,
            'participants_evolution' => 0,
            'participation_evolution' => 0,
            'tickets_evolution' => 0,
            'users_evolution' => 0,
            'planned' => 0,
            'ongoing' => 0,
            'completed' => 0,
            'planned_percent' => 0,
            'ongoing_percent' => 0,
            'completed_percent' => 0,
            'total' => 0,
            'upcoming_events' => [],
            'total_users_stats' => 0,
            'new_users_this_month' => 0,
            'admins_count' => 0,
            'organizers_count' => 0,
            'default_count' => 0,
            'sponsors_count' => 0,
            'admins_percent' => 0,
            'organizers_percent' => 0,
            'default_percent' => 0,
            'sponsors_percent' => 0,
        ];
    }
}