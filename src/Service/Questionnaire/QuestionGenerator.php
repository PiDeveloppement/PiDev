<?php

namespace App\Service\Questionnaire;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\Event\Event;

class QuestionGenerator
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    /**
     * Le nom de la variable $geminiApiKey doit correspondre 
     * exactement à l'argument défini dans services.yaml
     */
    public function __construct(HttpClientInterface $httpClient, string $geminiApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $geminiApiKey;
    }

    /**
     * Génère une question de quiz basée sur une description
     */
    public function generate(string $description): array
    {
        $prompt = "Tu es un assistant expert en quiz. À partir de la description suivante : '$description', 
        génère une question de quiz. 
        Réponds UNIQUEMENT avec un objet JSON strict ayant exactement ces clés : 
        'texte', 'reponse', 'points', 'option1', 'option2', 'option3'.
        Ne mets aucune balise Markdown comme ```json autour.";

        return $this->generateWithRetry($prompt);
    }

    /**
     * Génère une question de quiz basée sur un événement
     */
    public function generateFromEvent(Event $event): array
    {
        // Construire une description détaillée de l'événement
        $eventDescription = sprintf(
            "Événement: %s\nDescription: %s\nLieu: %s\nDate: %s\nCatégorie: %s",
            $event->getTitle(),
            $event->getDescription(),
            $event->getLocation(),
            $event->getStartDate() ? $event->getStartDate()->format('d/m/Y H:i') : 'Non définie',
            $event->getCategory()?->getName() ?? 'Non définie'
        );

        // Prompt optimisé pour obtenir un JSON pur basé sur l'événement
        $prompt = "Tu es un assistant expert en quiz. À partir des informations de l'événement suivant : 
        '$eventDescription'
        
        Génère une question de quiz pertinente sur cet événement. La question doit être spécifique et basée sur les détails fournis.
        
        Réponds UNIQUEMENT avec un objet JSON strict ayant exactement ces clés : 
        'texte' (la question), 'reponse' (la réponse correcte), 'points' (entre 5 et 20), 'option1', 'option2', 'option3' (les 3 fausses réponses).
        
        Les fausses réponses doivent être plausibles mais incorrectes.
        Ne mets aucune balise Markdown comme ```json autour.";

        return $this->generateWithRetry($prompt);
    }

    /**
     * Méthode robuste avec retry et fallback pour la génération
     */
    private function generateWithRetry(string $prompt): array
    {
        // Liste des modèles à essayer par ordre de préférence
        $models = [
            'gemini-2.5-flash',
            'gemini-2.5-pro', 
            'gemini-flash-latest',
            'gemini-pro-latest'
        ];

        $lastError = null;

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    return $this->callGeminiAPI($model, $prompt);
                } catch (\Exception $e) {
                    $lastError = $e;
                    
                    // Si c'est une erreur 503, on attend avant de réessayer
                    if (strpos($e->getMessage(), '503') !== false) {
                        if ($attempt < 3) {
                            sleep(2 * $attempt); // Attendre 2s, 4s entre les tentatives
                            continue;
                        }
                    }
                    
                    // Si c'est une erreur 404 (modèle non trouvé), on passe au modèle suivant
                    if (strpos($e->getMessage(), '404') !== false) {
                        break; // Sortir de la boucle de retry pour essayer le prochain modèle
                    }
                    
                    // Pour autres erreurs, on réessaye jusqu'à 3 fois
                    if ($attempt < 3) {
                        sleep(1); // Attendre 1s entre les tentatives
                    }
                }
            }
        }

        // Si tous les modèles ont échoué, générer une question par défaut
        return $this->generateFallbackQuestion();
    }

    /**
     * Appel à l'API Gemini avec un modèle spécifique
     */
    private function callGeminiAPI(string $model, string $prompt): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;

        $response = $this->httpClient->request('POST', $url, [
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.7
                ]
            ],
            'timeout' => 30
        ]);

        $content = $response->toArray();

        // Extraction du texte de la réponse Gemini
        if (!isset($content['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Réponse invalide de l'API Gemini.");
        }

        $jsonRaw = $content['candidates'][0]['content']['parts'][0]['text'];
        
        // Décodage du JSON
        $data = json_decode($jsonRaw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Erreur lors du décodage du JSON généré par l'IA.");
        }

        // Validation des données requises
        $requiredKeys = ['texte', 'reponse', 'points', 'option1', 'option2', 'option3'];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key]) || empty($data[$key])) {
                throw new \Exception("Donnée manquante dans la réponse IA: $key");
            }
        }

        return $data;
    }

    /**
     * Génère une question par défaut si l'IA n'est pas disponible
     */
    private function generateFallbackQuestion(): array
    {
        return [
            'texte' => 'Quelle est la capitale de la France ?',
            'reponse' => 'Paris',
            'points' => 10,
            'option1' => 'Lyon',
            'option2' => 'Marseille', 
            'option3' => 'Bordeaux'
        ];
    }
}