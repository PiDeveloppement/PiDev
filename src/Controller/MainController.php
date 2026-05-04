<?php

namespace App\Controller;

use App\Entity\User\UserModel;
use App\Service\User\UserService;
use App\Service\Role\RoleService;
use App\Repository\User\UserRepository;
use App\Repository\Role\RoleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Psr\Log\LoggerInterface;

class MainController extends AbstractController
{
    private array $pageInfo = [];

    public function __construct(
        private UserService $userService,
        private RoleService $roleService,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private LoggerInterface $logger
    ) {
        $this->initializePageInfo();
    }

    private function initializePageInfo(): void
    {
        $this->pageInfo = [
            'dashboard' => ['title' => 'Tableau de bord', 'subtitle' => 'Aperçu général de votre activité'],
            'events' => ['title' => 'Gestion des événements', 'subtitle' => 'Consultez et gérez tous vos événements'],
            'categories' => ['title' => 'Gestion des catégories', 'subtitle' => 'Gérez les catégories d\'événements'],
            'tickets' => ['title' => 'Gestion des billets', 'subtitle' => 'Gérez les billets et inscriptions'],
            'users' => ['title' => 'Gestion des participants', 'subtitle' => 'Gérez les participants'],
            'roles' => ['title' => 'Gestion des rôles', 'subtitle' => 'Gérez les différents rôles'],
            'inscriptions' => ['title' => 'Gestion des inscriptions', 'subtitle' => 'Gérez les inscriptions'],
            'sponsors' => ['title' => 'Gestion des sponsors', 'subtitle' => 'Gérez vos partenaires'],
            'sponsorsList' => ['title' => 'Liste des sponsors', 'subtitle' => 'Consultez tous les sponsors'],
            'sponsorPortal' => ['title' => 'Portail Sponsor', 'subtitle' => 'Accédez à votre espace sponsor'],
            'budget' => ['title' => 'Gestion du budget', 'subtitle' => 'Suivez vos finances'],
            'depenses' => ['title' => 'Gestion des dépenses', 'subtitle' => 'Suivez vos dépenses'],
            'salles' => ['title' => 'Gestion des salles', 'subtitle' => 'Gérez les salles et espaces'],
            'equipements' => ['title' => 'Gestion des équipements', 'subtitle' => 'Gérez le matériel'],
            'reservations' => ['title' => 'Gestion des réservations', 'subtitle' => 'Gérez les réservations'],
            'questions' => ['title' => 'Gestion des questions', 'subtitle' => 'Gérez les questions'],
            'reponses' => ['title' => 'Gestion des réponses', 'subtitle' => 'Consultez les réponses'],
            'resultats' => ['title' => 'Résultats', 'subtitle' => 'Statistiques et aperçu global'],
            'historique' => ['title' => 'Historique', 'subtitle' => 'Consultation des anciens scores'],
            'participantQuiz' => ['title' => 'Passer le Quiz', 'subtitle' => 'Interface d\'examen'],
            'settings' => ['title' => 'Paramètres', 'subtitle' => 'Configurez l\'application'],
            'profile' => ['title' => 'Mon profil', 'subtitle' => 'Consultez et modifiez vos informations'],
        ];
    }

  

    // ✅ Gardé - Dashboard (route renommée pour éviter le conflit avec le DashboardController)
    #[Route('/dashboard/main', name: 'app_main_dashboard')]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            $stats = [
                'total_roles' => $this->roleService->getTotalRolesCount(),
                'new_users_this_month' => $this->userService->getNewUsersThisMonthCount(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Erreur chargement stats dashboard: ' . $e->getMessage());
            $stats = [
                'total_roles' => 0,
                'new_users_this_month' => 0,
            ];
        }

        return $this->render('dashboard/dashboard.html.twig', [
            'pageInfo' => $this->pageInfo['dashboard'],
            'stats' => $stats,
            'user' => $this->getUser()
        ]);
    }

    // ✅ Gardé - API KPI
    #[Route('/api/kpi', name: 'app_kpi', methods: ['GET'])]
    public function getKpiData(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            $currentTitle = $this->pageInfo['dashboard']['title'];
            
            $data = [
                'participant' => $this->userService->getTotalUsersCount(),
                'role' => $this->roleService->getTotalRolesCount(),
            ];

            return $this->json([
                'success' => true,
                'data' => $data,
                'currentPage' => $currentTitle
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur API KPI: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Gardé - Recherche globale
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function globalSearch(Request $request): JsonResponse
    {
        $query = strtolower($request->query->get('q', ''));
        
        if (empty($query)) {
            return $this->json([]);
        }

        $results = [];

        if (str_contains('dashboard', $query)) {
            $results[] = '📊 Dashboard';
        }
        
        if (str_contains('événements', $query) || str_contains('events', $query)) {
            $results[] = '📅 Événements';
            $results[] = '   📋 Liste des événements';
            $results[] = '   🏷️ Catégories';
            $results[] = '   🎫 Billets';
        }
        
        if (str_contains('participants', $query) || str_contains('users', $query)) {
            $results[] = '👥 Participants';
            $results[] = '   👤 Rôles';
            $results[] = '   📝 Inscriptions';
        }
        
        if (str_contains('sponsors', $query)) {
            $results[] = '💼 Sponsors';
            $results[] = '   📋 Liste sponsors';
            $results[] = '   🔑 Portail Sponsor';
            $results[] = '   💰 Budget';
            $results[] = '   📄 Dépenses';
        }
        
        if (str_contains('ressources', $query)) {
            $results[] = '📦 Ressources';
            $results[] = '   💻 Équipements';
            $results[] = '   🏢 Salles';
            $results[] = '   📅 Réservations';
        }
        
        if (str_contains('questionnaires', $query)) {
            $results[] = '📝 Questionnaires';
            $results[] = '   ❓ Questions';
            $results[] = '   📊 Résultats';
            $results[] = '   📜 Historique';
            $results[] = '   🎯 Passer le Quiz';
        }
        
        if (str_contains('paramètres', $query) || str_contains('settings', $query)) {
            $results[] = '⚙️ Paramètres';
        }

        return $this->json($results);
    }

    // ✅ Gardé - Méthodes utilitaires (pas des routes)
    public function getUserInitials(UserInterface $user): string
    {
        if ($user instanceof UserModel) {
            return $user->getInitials();
        }
        return 'U';
    }

    public function getCurrentDateTime(): array
    {
        $now = new \DateTime();
        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            null,
            null,
            'EEEE dd MMMM yyyy'
        );
        
        return [
            'date' => ucfirst($formatter->format($now)),
            'time' => $now->format('H:i:s')
        ];
    }

    // ✅ Gardé - Méthodes KPI (pas des routes)
    public function showParticipantKPIs(): array
    {
        return [
            ['icon' => '👥', 'value' => $this->userService->getTotalUsersCount(), 'label' => 'Total Participants', 'color' => 'blue'],
            ['icon' => '📝', 'value' => $this->userService->getNewUsersThisMonthCount(), 'label' => 'Nouveaux ce mois', 'color' => 'green'],
        ];
    }

    public function showRoleKPIs(): array
    {
        $stats = $this->roleService->getRoleStatistics();
        return [
            ['icon' => '📋', 'value' => (string)($stats['total'] ?? 0), 'label' => 'Total Rôles', 'color' => 'green'],
            ['icon' => '👥', 'value' => (string)($stats['usage'][0]['userCount'] ?? 0), 'label' => 'Rôle le plus utilisé', 'color' => 'blue'],
        ];
    }

    public function createKPICard(string $icon, string $value, string $label, string $color): array
    {
        $colors = [
            'green' => ['bg' => '#d1f4e0', 'border' => '#95d5b2', 'value' => '#1b5e20', 'label' => '#2d6a4f'],
            'blue' => ['bg' => '#cfe2ff', 'border' => '#9ec5fe', 'value' => '#004085', 'label' => '#0056b3'],
            'amber' => ['bg' => '#fef3c7', 'border' => '#fcd34d', 'value' => '#92400e', 'label' => '#b45309'],
        ];

        $style = $colors[$color] ?? $colors['blue'];

        return [
            'icon' => $icon,
            'value' => $value,
            'label' => $label,
            'bgColor' => $style['bg'],
            'borderColor' => $style['border'],
            'valueColor' => $style['value'],
            'labelColor' => $style['label']
        ];
    }

    // ✅ Gardé - Logout
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
