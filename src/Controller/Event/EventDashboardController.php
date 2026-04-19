<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Category;
use App\Entity\Event\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/event/dashboard')]
class EventDashboardController extends AbstractController
{
    #[Route('/', name: 'app_event_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(Category::class)->findBy([], ['name' => 'ASC']);

        return $this->render('event/dashboard.html.twig', [
            'pageInfo' => [
                'title' => 'Dashboard Statistiques',
                'subtitle' => 'Aperçu général de vos événements'
            ],
            'dashboardCategories' => $categories,
        ]);
    }

    #[Route('/stats', name: 'app_event_dashboard_stats', methods: ['GET'])]
    public function stats(Request $request, EntityManagerInterface $em, ChartBuilderInterface $chartBuilder): JsonResponse
    {
        $months = $request->query->getInt('months', 6);
        if (!in_array($months, [3, 6, 12], true)) {
            $months = 6;
        }

        $categoryId = $request->query->getInt('categoryId', 0);
        $now = new \DateTime();
        $periodStart = (new \DateTime())->modify(sprintf('-%d months', $months - 1))->modify('first day of this month')->setTime(0, 0);

        $events = $em->getRepository(Event::class)->findAll();
        $categories = $em->getRepository(Category::class)->findAll();
        $tickets = $em->getRepository(Ticket::class)->findAll();

        $filteredEvents = array_values(array_filter($events, function (Event $event) use ($periodStart, $categoryId) {
            $startDate = $event->getStartDate();
            if (!$startDate || $startDate < $periodStart) {
                return false;
            }

            if ($categoryId > 0) {
                return $event->getCategory()?->getId() === $categoryId;
            }

            return true;
        }));

        $filteredTickets = array_values(array_filter($tickets, function (Ticket $ticket) use ($periodStart, $filteredEvents) {
            $createdAt = $ticket->getCreatedAt();
            if (!$createdAt || $createdAt < $periodStart) {
                return false;
            }

            $eventId = $ticket->getEvent()?->getId();
            if (!$eventId) {
                return false;
            }

            foreach ($filteredEvents as $event) {
                if ($event->getId() === $eventId) {
                    return true;
                }
            }

            return false;
        }));

        // ==================== KPIs ====================
        $totalEvents = count($filteredEvents);
        $totalTickets = count($filteredTickets);
        $totalCategories = count($categories);

        $aVenir = count(array_filter($filteredEvents, fn(Event $e) => $e->getStartDate() > $now));
        $enCours = count(array_filter($filteredEvents, fn(Event $e) => $e->getStartDate() <= $now && $e->getEndDate() >= $now));
        $termines = count(array_filter($filteredEvents, fn(Event $e) => $e->getEndDate() < $now));

        $revenus = array_sum(array_map(fn(Event $e) => $e->isFree() ? 0 : (float)$e->getTicketPrice(), $filteredEvents));

        // ==================== EVENTS PAR CATEGORIE ====================
        $eventsByCategory = [];
        foreach ($categories as $cat) {
            $count = count(array_filter($filteredEvents, fn(Event $e) => $e->getCategory()?->getId() === $cat->getId()));
            if ($count > 0) {
                $eventsByCategory[] = [
                    'label' => $cat->getName(),
                    'value' => $count,
                    'color' => $cat->getColor() ?? '#1565c0'
                ];
            }
        }

        // ==================== EVENTS PAR MOIS ====================
        $eventsByMonth = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = (new \DateTime())->modify("-$i months");
            $monthKey = $month->format('Y-m');
            $monthLabel = $month->format('M Y');
            $count = count(array_filter($filteredEvents, function(Event $e) use ($monthKey) {
                return $e->getStartDate()?->format('Y-m') === $monthKey;
            }));
            $eventsByMonth[] = ['label' => $monthLabel, 'value' => $count];
        }

        // ==================== TICKETS PAR MOIS ====================
        $ticketsByMonth = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = (new \DateTime())->modify("-$i months");
            $monthKey = $month->format('Y-m');
            $monthLabel = $month->format('M Y');
            $count = count(array_filter($filteredTickets, function(Ticket $t) use ($monthKey) {
                return $t->getCreatedAt()?->format('Y-m') === $monthKey;
            }));
            $ticketsByMonth[] = ['label' => $monthLabel, 'value' => $count];
        }

        // ==================== EVENTS GRATUITS VS PAYANTS ====================
        $gratuits = count(array_filter($filteredEvents, fn(Event $e) => $e->isFree()));
        $payants = $totalEvents - $gratuits;

        $eventsByCategoryLabels = array_map(static fn(array $row) => $row['label'], $eventsByCategory);
        $eventsByCategoryValues = array_map(static fn(array $row) => $row['value'], $eventsByCategory);
        $eventsByCategoryColors = array_map(static fn(array $row) => $row['color'], $eventsByCategory);

        $eventsByMonthLabels = array_map(static fn(array $row) => $row['label'], $eventsByMonth);
        $eventsByMonthValues = array_map(static fn(array $row) => $row['value'], $eventsByMonth);

        $ticketsByMonthLabels = array_map(static fn(array $row) => $row['label'], $ticketsByMonth);
        $ticketsByMonthValues = array_map(static fn(array $row) => $row['value'], $ticketsByMonth);

        $prixDistributionLabels = array_map(static fn(array $row) => $row['label'], [
            ['label' => 'Gratuits', 'value' => $gratuits, 'color' => '#16a34a'],
            ['label' => 'Payants', 'value' => $payants, 'color' => '#1565c0'],
        ]);
        $prixDistributionValues = [$gratuits, $payants];
        $prixDistributionColors = ['#16a34a', '#1565c0'];

        $eventsByCategoryChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT)
            ->setData([
                'labels' => $eventsByCategoryLabels,
                'datasets' => [[
                    'data' => $eventsByCategoryValues,
                    'backgroundColor' => $eventsByCategoryColors,
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ]],
            ]);

        $eventsByMonthChart = $chartBuilder->createChart(Chart::TYPE_BAR)
            ->setData([
                'labels' => $eventsByMonthLabels,
                'datasets' => [[
                    'label' => 'Événements',
                    'data' => $eventsByMonthValues,
                    'backgroundColor' => 'rgba(21, 101, 192, 0.75)',
                    'borderColor' => '#1565c0',
                    'borderWidth' => 1,
                ]],
            ]);

        $ticketsByMonthChart = $chartBuilder->createChart(Chart::TYPE_BAR)
            ->setData([
                'labels' => $ticketsByMonthLabels,
                'datasets' => [[
                    'label' => 'Billets',
                    'data' => $ticketsByMonthValues,
                    'backgroundColor' => 'rgba(13, 148, 136, 0.75)',
                    'borderColor' => '#0d9488',
                    'borderWidth' => 1,
                ]],
            ]);

        $prixDistributionChart = $chartBuilder->createChart(Chart::TYPE_PIE)
            ->setData([
                'labels' => $prixDistributionLabels,
                'datasets' => [[
                    'data' => $prixDistributionValues,
                    'backgroundColor' => $prixDistributionColors,
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ]],
            ]);

        $upcoming = array_values(array_filter($filteredEvents, fn(Event $e) => $e->getStartDate() && $e->getStartDate() >= $now));
        usort($upcoming, fn(Event $a, Event $b) => $a->getStartDate() <=> $b->getStartDate());
        $upcoming = array_slice($upcoming, 0, 5);

        $upcomingEvents = array_map(static function (Event $event): array {
            return [
                'title' => $event->getTitle(),
                'category' => $event->getCategory()?->getName() ?? 'Sans catégorie',
                'startsAt' => $event->getStartDate()?->format('d/m/Y H:i') ?? '-',
                'isFree' => (bool) $event->isFree(),
            ];
        }, $upcoming);

        return $this->json([
            'kpis' => [
                'totalEvents' => $totalEvents,
                'totalTickets' => $totalTickets,
                'totalCategories' => $totalCategories,
                'revenus' => $revenus,
                'aVenir' => $aVenir,
                'enCours' => $enCours,
                'termines' => $termines,
            ],
            'eventsByCategory' => $eventsByCategory,
            'eventsByMonth' => $eventsByMonth,
            'ticketsByMonth' => $ticketsByMonth,
            'prixDistribution' => [
                ['label' => 'Gratuits', 'value' => $gratuits, 'color' => '#16a34a'],
                ['label' => 'Payants', 'value' => $payants, 'color' => '#1565c0'],
            ],
            'charts' => [
                'eventsByCategory' => $eventsByCategoryChart->createView(),
                'eventsByMonth' => $eventsByMonthChart->createView(),
                'ticketsByMonth' => $ticketsByMonthChart->createView(),
                'prixDistribution' => $prixDistributionChart->createView(),
            ],
            'upcomingEvents' => $upcomingEvents,
            'meta' => [
                'months' => $months,
                'categoryId' => $categoryId,
                'generatedAt' => (new \DateTime())->format('d/m/Y H:i:s'),
            ],
        ]);
    }
}