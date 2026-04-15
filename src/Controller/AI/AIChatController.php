<?php

namespace App\Controller\AI;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/api/ai-chat')]
class AIChatController extends AbstractController
{
    private HttpClientInterface $httpClient;
    private ParameterBagInterface $params;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params)
    {
        $this->httpClient = $httpClient;
        $this->params = $params;
    }

    #[Route('/send', name: 'app_ai_chat_send', methods: ['POST'])]
    public function sendMessage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        if (empty($message)) {
            return new JsonResponse(['error' => 'Message vide'], 400);
        }

        try {
            // Vérifier si la clé API Gemini est configurée
            $apiKey = $_ENV['GEMINI_API_KEY'] ?? null;
            
            if (empty($apiKey) || $apiKey === 'your_gemini_api_key_here') {
                // Réponse simulée si pas de clé API
                $response = $this->getSimulatedResponse($message);
                return new JsonResponse([
                    'response' => $response,
                    'isSimulated' => true
                ]);
            }

            // Appel à l'API Gemini
            $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => 'Tu es un assistant de gestion pour EventFlow, une plateforme de gestion d\'événements. Aide les utilisateurs avec des questions sur les événements, les utilisateurs, les budgets, etc. Sois concis et professionnel. Réponds en français.

Question utilisateur: ' . $message
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 500
                    ]
                ]
            ]);

            $result = $response->toArray();
            $aiResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Désolé, je n\'ai pas pu générer de réponse.';

            return new JsonResponse([
                'response' => $aiResponse,
                'isSimulated' => false
            ]);

        } catch (\Exception $e) {
            // En cas d'erreur, retourner une réponse simulée
            $response = $this->getSimulatedResponse($message);
            return new JsonResponse([
                'response' => $response,
                'isSimulated' => true,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getSimulatedResponse(string $message): string
    {
        $message = strtolower($message);

        // Réponses simulées basées sur des mots-clés
        if (str_contains($message, 'utilisateur') || str_contains($message, 'inscrit')) {
            return "Pour voir les statistiques des utilisateurs, allez dans la section 'Utilisateurs' du tableau de bord. Vous pouvez y voir le nombre total d'utilisateurs, les nouveaux inscrits et les administrateurs.";
        }

        if (str_contains($message, 'admin') || str_contains($message, 'administrateur')) {
            return "La liste des administrateurs est accessible dans le menu 'Utilisateurs' > 'Administrateurs'. Vous pouvez y ajouter, modifier ou supprimer des administrateurs.";
        }

        if (str_contains($message, 'événement') || str_contains($message, 'event')) {
            return "Pour gérer les événements, utilisez le menu 'Événements'. Vous pouvez y créer, modifier et supprimer des événements, ainsi que voir les statistiques de participation.";
        }

        if (str_contains($message, 'budget') || str_contains($message, 'dépense')) {
            return "Les budgets et dépenses sont gérés dans la section 'Budget'. Vous pouvez suivre les dépenses par événement et gérer les budgets sponsorisés.";
        }

        if (str_contains($message, 'sponsor')) {
            return "La gestion des sponsors se trouve dans le menu 'Sponsors'. Vous pouvez y gérer les contrats, les budgets et l'historique des sponsors.";
        }

        if (str_contains($message, 'salle') || str_contains($message, 'ressource')) {
            return "Les salles et équipements sont gérés dans la section 'Ressources'. Vous pouvez réserver des salles et gérer les équipements disponibles.";
        }

        if (str_contains($message, 'bonjour') || str_contains($message, 'hello') || str_contains($message, 'salut')) {
            return "Bonjour ! Je suis votre assistant EventFlow. Comment puis-je vous aider aujourd'hui ?";
        }

        if (str_contains($message, 'aide') || str_contains($message, 'help')) {
            return "Je peux vous aider avec : la gestion des utilisateurs, des événements, des budgets, des sponsors et des ressources. Posez-moi vos questions !";
        }

        return "Je comprends votre question sur \"" . htmlspecialchars($message) . "\". Pour l'instant, je fonctionne en mode simulation. Configurez une clé API Gemini dans le fichier .env (GEMINI_API_KEY) pour activer les réponses IA avancées.";
    }
}
