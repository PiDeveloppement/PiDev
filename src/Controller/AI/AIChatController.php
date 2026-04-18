<?php

namespace App\Controller\AI;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Repository\User\UserRepository;
use App\Repository\Event\EventRepository;
use App\Repository\Event\CategoryRepository;
use App\Repository\Event\TicketRepository;
use App\Repository\Budget\BudgetRepository;
use App\Repository\Depense\DepenseRepository;
use App\Repository\Sponsor\SponsorRepository;
use App\Repository\Resource\SalleRepository;
use App\Repository\Resource\EquipementRepository;
use App\Repository\Resource\ReservationResourceRepository;
use App\Repository\Questionnaire\QuestionRepository;
use App\Repository\Questionnaire\FeedbackRepository;
use App\Repository\Role\RoleRepository;

#[Route('/api/ai-chat')]
class AIChatController extends AbstractController
{
    private HttpClientInterface $httpClient;
    private ParameterBagInterface $params;
    private UserRepository $userRepository;
    private EventRepository $eventRepository;
    private CategoryRepository $categoryRepository;
    private TicketRepository $ticketRepository;
    private BudgetRepository $budgetRepository;
    private DepenseRepository $depenseRepository;
    private SponsorRepository $sponsorRepository;
    private SalleRepository $salleRepository;
    private EquipementRepository $equipementRepository;
    private ReservationResourceRepository $reservationResourceRepository;
    private QuestionRepository $questionRepository;
    private FeedbackRepository $feedbackRepository;
    private RoleRepository $roleRepository;

    public function __construct(
        HttpClientInterface $httpClient,
        ParameterBagInterface $params,
        UserRepository $userRepository,
        EventRepository $eventRepository,
        CategoryRepository $categoryRepository,
        TicketRepository $ticketRepository,
        BudgetRepository $budgetRepository,
        DepenseRepository $depenseRepository,
        SponsorRepository $sponsorRepository,
        SalleRepository $salleRepository,
        EquipementRepository $equipementRepository,
        ReservationResourceRepository $reservationResourceRepository,
        QuestionRepository $questionRepository,
        FeedbackRepository $feedbackRepository,
        RoleRepository $roleRepository
    ) {
        $this->httpClient = $httpClient;
        $this->params = $params;
        $this->userRepository = $userRepository;
        $this->eventRepository = $eventRepository;
        $this->categoryRepository = $categoryRepository;
        $this->ticketRepository = $ticketRepository;
        $this->budgetRepository = $budgetRepository;
        $this->depenseRepository = $depenseRepository;
        $this->sponsorRepository = $sponsorRepository;
        $this->salleRepository = $salleRepository;
        $this->equipementRepository = $equipementRepository;
        $this->reservationResourceRepository = $reservationResourceRepository;
        $this->questionRepository = $questionRepository;
        $this->feedbackRepository = $feedbackRepository;
        $this->roleRepository = $roleRepository;
    }

   #[Route('/send', name: 'app_ai_chat_send', methods: ['POST'])]
public function sendMessage(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $message = $data['message'] ?? '';

    if (empty($message)) {
        return new JsonResponse(['error' => 'Message vide'], 400);
    }

    // ✅ Utiliser $_ENV ou getenv() — Symfony charge automatiquement le .env
    $groqApiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?? null;

    // Fetch real database statistics
    $statistics = $this->getDatabaseStatistics();

    try {
        if (!empty($groqApiKey)) {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                     'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->buildSystemPrompt($statistics)
                        ],
                        [
                            'role' => 'user',
                            'content' => $message
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500
                ]
            ]);

            $result = $response->toArray();
            $aiResponse = $result['choices'][0]['message']['content'] ?? 'Désolé, je n\'ai pas pu générer de réponse.';

            return new JsonResponse([
                'response' => $aiResponse,
                'isSimulated' => false,
            ]);
        }

        // Fallback simulation with real data
        return new JsonResponse([
            'response' => $this->getSimulatedResponse($message, $statistics),
            'isSimulated' => true,
            'debug' => 'GROQ_API_KEY non trouvée dans l\'environnement'
        ]);

    } catch (\Exception $e) {
        return new JsonResponse([
            'response' => $this->getSimulatedResponse($message, $statistics),
            'isSimulated' => true,
            'debug' => 'Erreur API Groq: ' . $e->getMessage()
        ]);
    }
}

    private function getDatabaseStatistics(): array
    {
        $userCountByRole = $this->userRepository->countByRole();
        $roleCounts = [];
        foreach ($userCountByRole as $roleData) {
            $roleCounts[$roleData['role']] = $roleData['count'];
        }

        $totalUsers = $this->userRepository->countAll();
        $newUsersThisMonth = $this->userRepository->countNewThisMonth();

        $allEvents = $this->eventRepository->findAll();
        $totalEvents = count($allEvents);
        $publishedEvents = count($this->eventRepository->findByStatus('PUBLISHED'));
        $draftEvents = count($this->eventRepository->findByStatus('DRAFT'));

        $now = new \DateTime();
        $upcomingEvents = 0;
        $ongoingEvents = 0;
        $pastEvents = 0;
        $upcomingEventsDetails = [];

        foreach ($allEvents as $event) {
            if ($event->getEndDate() && $event->getEndDate() < $now) {
                $pastEvents++;
            } elseif ($event->getStartDate() && $event->getStartDate() > $now) {
                $upcomingEvents++;
                $upcomingEventsDetails[] = [
                    'title' => $event->getTitle(),
                    'start_date' => $event->getStartDate() ? $event->getStartDate()->format('d/m/Y H:i') : 'N/A',
                    'location' => $event->getLocation() ?? 'N/A',
                ];
            } elseif ($event->getStartDate() && $event->getEndDate() &&
                      $event->getStartDate() <= $now && $event->getEndDate() >= $now) {
                $ongoingEvents++;
            }
        }

        $totalBudgets = count($this->budgetRepository->findAll());
        $totalDepenses = count($this->depenseRepository->findAll());
        $totalSponsors = $this->sponsorRepository->getTotalSponsors();
        $totalSponsorContribution = $this->sponsorRepository->getTotalContribution();
        $totalCategories = count($this->categoryRepository->findAll());
        $totalTickets = count($this->ticketRepository->findAll());
        $totalSalles = count($this->salleRepository->findAll());
        $totalEquipements = count($this->equipementRepository->findAll());
        $totalReservations = count($this->reservationResourceRepository->findAll());
        $totalQuestions = count($this->questionRepository->findAll());
        $totalFeedbacks = count($this->feedbackRepository->findAll());
        $totalRoles = count($this->roleRepository->findAll());

        return [
            'total_users' => $totalUsers,
            'users_by_role' => $roleCounts,
            'new_users_this_month' => $newUsersThisMonth,
            'total_events' => $totalEvents,
            'published_events' => $publishedEvents,
            'draft_events' => $draftEvents,
            'upcoming_events' => $upcomingEvents,
            'upcoming_events_details' => $upcomingEventsDetails,
            'ongoing_events' => $ongoingEvents,
            'past_events' => $pastEvents,
            'total_budgets' => $totalBudgets,
            'total_depenses' => $totalDepenses,
            'total_sponsors' => $totalSponsors,
            'total_sponsor_contribution' => $totalSponsorContribution,
            'total_categories' => $totalCategories,
            'total_tickets' => $totalTickets,
            'total_salles' => $totalSalles,
            'total_equipements' => $totalEquipements,
            'total_reservations' => $totalReservations,
            'total_questions' => $totalQuestions,
            'total_feedbacks' => $totalFeedbacks,
            'total_roles' => $totalRoles,
        ];
    }

    private function buildSystemPrompt(array $statistics): string
    {
        $roleBreakdown = [];
        foreach ($statistics['users_by_role'] as $role => $count) {
            $roleBreakdown[] = "- **{$role}** : {$count} utilisateurs";
        }
        $roleBreakdownStr = implode("\n", $roleBreakdown);

        $upcomingEventsStr = '';
        if (!empty($statistics['upcoming_events_details'])) {
            $upcomingEventsStr .= "\nDétails des événements à venir :\n";
            foreach ($statistics['upcoming_events_details'] as $index => $event) {
                $upcomingEventsStr .= ($index + 1) . ". **{$event['title']}** - {$event['start_date']} à {$event['location']}\n";
            }
        } else {
            $upcomingEventsStr = "\nAucun événement à venir pour le moment.";
        }

        return "Tu es un assistant de gestion pour EventFlow, une plateforme de gestion d'événements. " .
               "Aide les utilisateurs avec des questions sur les événements, les utilisateurs, les budgets, les sponsors, les ressources, etc. " .
               "Sois concis et professionnel. Réponds en français.\n\n" .
               "Voici les statistiques actuelles de la plateforme (à jour en temps réel) :\n\n" .
               "**Utilisateurs :**\n" .
               "- Total : {$statistics['total_users']} utilisateurs\n" .
               "- Nouveaux ce mois : {$statistics['new_users_this_month']} utilisateurs\n" .
               "- Par rôle :\n{$roleBreakdownStr}\n\n" .
               "**Événements :**\n" .
               "- Total : {$statistics['total_events']} événements\n" .
               "- Publiés : {$statistics['published_events']} événements\n" .
               "- Brouillons : {$statistics['draft_events']} événements\n" .
               "- À venir : {$statistics['upcoming_events']} événements{$upcomingEventsStr}\n" .
               "- En cours : {$statistics['ongoing_events']} événements\n" .
               "- Terminés : {$statistics['past_events']} événements\n" .
               "- Catégories : {$statistics['total_categories']} catégories\n" .
               "- Billets : {$statistics['total_tickets']} billets\n\n" .
               "**Budgets et Dépenses :**\n" .
               "- Budgets : {$statistics['total_budgets']} budgets\n" .
               "- Dépenses : {$statistics['total_depenses']} dépenses\n\n" .
               "**Sponsors :**\n" .
               "- Total : {$statistics['total_sponsors']} sponsors\n" .
               "- Contribution totale : {$statistics['total_sponsor_contribution']} DT\n\n" .
               "**Ressources :**\n" .
               "- Salles : {$statistics['total_salles']} salles\n" .
               "- Équipements : {$statistics['total_equipements']} équipements\n" .
               "- Réservations : {$statistics['total_reservations']} réservations\n\n" .
               "**Questionnaires :**\n" .
               "- Questions : {$statistics['total_questions']} questions\n" .
               "- Feedbacks : {$statistics['total_feedbacks']} feedbacks\n\n" .
               "**Système :**\n" .
               "- Rôles : {$statistics['total_roles']} rôles\n\n" .
               "IMPORTANT : Lorsque l'utilisateur demande des statistiques ou des chiffres, utilise TOUJOURS ces données réelles. " .
               "N'invente jamais de chiffres. Si une information n'est pas disponible dans ces statistiques, indique-le clairement.";
    }

    private function getSimulatedResponse(string $message, array $statistics): string
    {
        $message = strtolower($message);

        // Réponses simulées basées sur des mots-clés avec données réelles
        if (str_contains($message, 'utilisateur') || str_contains($message, 'inscrit')) {
            $roleBreakdown = [];
            foreach ($statistics['users_by_role'] as $role => $count) {
                $roleBreakdown[] = "- **{$role}** : {$count} utilisateurs";
            }
            $roleBreakdownStr = implode("\n", $roleBreakdown);

            return "Il y a actuellement **{$statistics['total_users']}** utilisateurs inscrits dans EventFlow.\n\n" .
                   "Répartition par rôle :\n{$roleBreakdownStr}\n\n" .
                   "Nouveaux inscrits ce mois : **{$statistics['new_users_this_month']}** utilisateurs.";
        }

        if (str_contains($message, 'admin') || str_contains($message, 'administrateur')) {
            return "La liste des administrateurs est accessible dans le menu 'Utilisateurs' > 'Administrateurs'. Vous pouvez y ajouter, modifier ou supprimer des administrateurs.";
        }

        if (str_contains($message, 'événement') || str_contains($message, 'event')) {
            return "Il y a actuellement **{$statistics['total_events']}** événements dans EventFlow.\n\n" .
                   "- Publiés : {$statistics['published_events']}\n" .
                   "- Brouillons : {$statistics['draft_events']}\n" .
                   "- À venir : {$statistics['upcoming_events']}\n" .
                   "- En cours : {$statistics['ongoing_events']}\n" .
                   "- Terminés : {$statistics['past_events']}\n\n" .
                   "Pour gérer les événements, utilisez le menu 'Événements'.";
        }

        if (str_contains($message, 'budget') || str_contains($message, 'dépense')) {
            return "Il y a actuellement **{$statistics['total_budgets']}** budgets gérés dans EventFlow.\n\n" .
                   "Les budgets et dépenses sont gérés dans la section 'Budget'. Vous pouvez suivre les dépenses par événement et gérer les budgets sponsorisés.";
        }

        if (str_contains($message, 'sponsor')) {
            return "Il y a actuellement **{$statistics['total_sponsors']}** sponsors dans EventFlow.\n\n" .
                   "Contribution totale des sponsors : **{$statistics['total_sponsor_contribution']} DT**\n\n" .
                   "La gestion des sponsors se trouve dans le menu 'Sponsors'. Vous pouvez y gérer les contrats, les budgets et l'historique des sponsors.";
        }

        if (str_contains($message, 'salle') || str_contains($message, 'ressource')) {
            return "Il y a actuellement **{$statistics['total_salles']}** salles et **{$statistics['total_equipements']}** équipements disponibles.\n\n" .
                   "Réservations totales : **{$statistics['total_reservations']}**\n\n" .
                   "Les salles et équipements sont gérés dans la section 'Ressources'. Vous pouvez réserver des salles et gérer les équipements disponibles.";
        }

        if (str_contains($message, 'catégorie') || str_contains($message, 'category')) {
            return "Il y a actuellement **{$statistics['total_categories']}** catégories d'événements dans EventFlow.\n\n" .
                   "Pour gérer les catégories, utilisez le menu 'Catégories'.";
        }

        if (str_contains($message, 'billet') || str_contains($message, 'ticket')) {
            return "Il y a actuellement **{$statistics['total_tickets']}** billets émis dans EventFlow.\n\n" .
                   "La gestion des billets se trouve dans le menu 'Billets'.";
        }

        if (str_contains($message, 'question') || str_contains($message, 'feedback') || str_contains($message, 'sondage')) {
            return "Il y a actuellement **{$statistics['total_questions']}** questions et **{$statistics['total_feedbacks']}** feedbacks collectés.\n\n" .
                   "Les questionnaires et feedbacks sont gérés dans la section 'Questionnaires'.";
        }

        if (str_contains($message, 'dépense') || str_contains($message, 'depense')) {
            return "Il y a actuellement **{$statistics['total_depenses']}** dépenses enregistrées.\n\n" .
                   "Les dépenses sont gérées dans la section 'Dépenses'.";
        }

        if (str_contains($message, 'bonjour') || str_contains($message, 'hello') || str_contains($message, 'salut')) {
            return "Bonjour ! Je suis votre assistant EventFlow. Comment puis-je vous aider aujourd'hui ?";
        }

        if (str_contains($message, 'aide') || str_contains($message, 'help')) {
            return "Je peux vous aider avec : la gestion des utilisateurs, des événements, des budgets, des sponsors et des ressources. Posez-moi vos questions !";
        }

        return "Je comprends votre question sur \"" . htmlspecialchars($message) . "\". Pour l'instant, je fonctionne en mode simulation. Configurez une clé API Groq dans le fichier .env (GROQ_API_KEY) pour activer les réponses IA avancées.";
    }
}
