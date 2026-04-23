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
     */
    public function analyzeRisk(array $recaptchaResult): array
    {
        $score = $recaptchaResult['score'] ?? 0;
        $action = $recaptchaResult['action'] ?? '';
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
            'score' => $score,
            'recommendations' => $recommendations,
            'allow_submission' => $riskLevel !== 'high' && $this->isGoodForQuiz($score)
        ];
    }

    /**
     * Validation spécifique pour les feedbacks de questionnaire
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
            'recaptcha_data' => $result
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
