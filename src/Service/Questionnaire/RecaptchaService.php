<?php

namespace App\Service\Questionnaire;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service reCAPTCHA spécialisé pour les questionnaires
 * Empêche les fake feedbacks et les bots de soumettre des quiz
 */
class RecaptchaService
{
    private string $secretKey;
    private HttpClientInterface $client;
    private float $scoreThreshold;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->secretKey = $_ENV['RECAPTCHA_SECRET_KEY'] ?? '';
        $this->scoreThreshold = (float)($_ENV['RECAPTCHA_SCORE_THRESHOLD'] ?? 0.5);
    }

    /**
     * Vérifie le token reCAPTCHA avec Google pour les questionnaires
     * @return array{success: bool, score: float, action: string, challenge_ts: string, hostname: string, error_codes?: array<int, string>}
     */
    public function verify(string $token, ?string $remoteIp = null): array
    {
        $response = $this->client->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]
        ]);

        $data = $response->toArray();

        return [
            'success' => $data['success'] ?? false,
            'score' => $data['score'] ?? 0,
            'action' => $data['action'] ?? null,
            'challenge_ts' => $data['challenge_ts'] ?? null,
            'hostname' => $data['hostname'] ?? null,
            'error_codes' => $data['error-codes'] ?? [],
            'is_human' => ($data['success'] ?? false) && ($data['score'] ?? 0) >= $this->scoreThreshold
        ];
    }

    /**
     * Vérifie si le score est suffisant pour considérer l'utilisateur comme humain
     * Spécifique aux questionnaires (plus strict)
     */
    public function isHuman(float $score): bool
    {
        return $score >= $this->scoreThreshold;
    }

    /**
     * Vérifie si le score est bon pour un questionnaire
     * Score plus élevé requis pour les quiz
     */
    public function isGoodForQuiz(float $score): bool
    {
        return $score >= max($this->scoreThreshold, 0.6); // Au moins 0.6 pour les quiz
    }

    /**
     * Génère le HTML pour le widget reCAPTCHA pour les questionnaires
     */
    public function getWidgetHtml(string $action = 'quiz_start'): string
    {
        $siteKey = $this->getSiteKey();
        
        return sprintf(
            '<script src="https://www.google.com/recaptcha/api.js?render=%s"></script>
            <script>
                function executeRecaptcha() {
                    grecaptcha.ready(function() {
                        grecaptcha.execute("%s", {action: "%s"}).then(function(token) {
                            document.getElementById("recaptcha-token").value = token;
                            document.getElementById("quiz-form").submit();
                        });
                    });
                }
            </script>
            <input type="hidden" id="recaptcha-token" name="recaptcha_token">',
            $siteKey,
            $siteKey,
            $action
        );
    }

    /**
     * Génère le JavaScript pour la vérification automatique
     */
    public function getAutoVerifyScript(string $action = 'quiz_submit'): string
    {
        $siteKey = $this->getSiteKey();
        
        return sprintf(
            '<script>
                function autoVerifyRecaptcha(callback) {
                    grecaptcha.ready(function() {
                        grecaptcha.execute("%s", {action: "%s"}).then(function(token) {
                            callback(token);
                        });
                    });
                }
            </script>',
            $siteKey,
            $action
        );
    }

    /**
     * Analyse le risque pour les questionnaires
     * @param array{success: bool, score: float, action: string, error_codes?: array<int, string>} $recaptchaResult
     * @return array{risk_level: string, allow_submission: bool, recommendations: array<int, string>, confidence: float}
     */
    public function analyzeRisk(array $recaptchaResult): array
    {
        $score = $recaptchaResult['score'];
        $action = $recaptchaResult['action'];
        $errors = $recaptchaResult['error_codes'] ?? [];

        $riskLevel = 'low';
        $recommendations = [];

        if ($score < 0.3) {
            $riskLevel = 'high';
            $recommendations[] = 'Score très bas - Probablement un bot';
        } elseif ($score < 0.6) {
            $riskLevel = 'medium';
            $recommendations[] = 'Score faible - Vérification supplémentaire recommandée';
        }

        if ($action !== 'quiz_start' && $action !== 'quiz_submit') {
            $riskLevel = 'medium';
            $recommendations[] = 'Action reCAPTCHA invalide';
        }

        if (!empty($errors)) {
            $riskLevel = 'high';
            $recommendations[] = 'Erreurs reCAPTCHA: ' . implode(', ', $errors);
        }

        return [
            'risk_level' => $riskLevel,
            'allow_submission' => $riskLevel !== 'high' && $this->isGoodForQuiz($score),
            'recommendations' => $recommendations,
            'confidence' => $this->calculateConfidence($score, $action, $errors)
        ];
    }

    /**
     * Validation spécifique pour les feedbacks de questionnaire
     * @return array{valid: bool, risk_analysis: array{risk_level: string, allow_submission: bool, recommendations: array<int, string>, confidence: float}, score: float, timestamp: string}
     */
    public function validateForFeedback(string $token, ?string $remoteIp = null): array
    {
        $result = $this->verify($token, $remoteIp);
        $risk = $this->analyzeRisk($result);

        // Pour les feedbacks, on est un peu plus strict
        $isValid = $result['success'] && 
                  $this->isHuman($result['score']) && 
                  $risk['allow_submission'];

        return [
            'valid' => $isValid,
            'risk_analysis' => $risk,
            'score' => $result['score'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Récupère la clé site reCAPTCHA
     */
    private function getSiteKey(): string
    {
        // Utiliser le service de paramètres ou une valeur par défaut
        return $_ENV['RECAPTCHA_SITE_KEY'] ?? '';
    }

    /**
     * Obtient des statistiques sur les vérifications
     * @return array{total_verifications: int, success_rate: float, average_score: float, blocked_attempts: int}
     */
    public function getVerificationStats(): array
    {
        // TODO: Implémenter le suivi des statistiques de vérification
        return [
            'total_verifications' => 0,
            'success_rate' => 0,
            'average_score' => 0,
            'blocked_attempts' => 0
        ];
    }

    /**
     * Calcule un score de confiance basé sur le score reCAPTCHA, l'action et les erreurs
     * @param array<int, string> $errors
     */
    private function calculateConfidence(float $score, string $action, array $errors): float
    {
        $confidence = $score;
        
        // Réduire la confiance si l'action n'est pas valide
        if (!$this->isValidAction($action)) {
            $confidence *= 0.5;
        }
        
        // Réduire la confiance s'il y a des erreurs
        if (!empty($errors)) {
            $confidence *= 0.3;
        }
        
        return max(0.0, min(1.0, $confidence));
    }

    /**
     * Configure le seuil de score pour les questionnaires
     */
    public function setQuizThreshold(float $threshold): void
    {
        $this->scoreThreshold = max(0.1, min(1.0, $threshold));
    }

    /**
     * Obtient le seuil actuel
     */
    public function getCurrentThreshold(): float
    {
        return $this->scoreThreshold;
    }
}