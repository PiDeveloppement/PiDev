<?php

namespace App\Service\User;

use Psr\Log\LoggerInterface;
use Twilio\Rest\Client;

class WhatsAppService
{
    // Configuration Twilio
    private const WHATSAPP_SANDBOX_NUMBER = '+14155238886';
    private const SANDBOX_INVITE_CODE = 'orange-popsicle';

    private ?Client $twilioClient = null;
    private ?string $accountSid;
    private ?string $authToken;

    public function __construct(
        private LoggerInterface $logger
    ) {
        // Charger les variables d'environnement
        $this->accountSid = $_ENV['TWILIO_ACCOUNT_SID'] ?? $_SERVER['TWILIO_ACCOUNT_SID'] ?? null;
        $this->authToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? $_SERVER['TWILIO_AUTH_TOKEN'] ?? null;

        $this->initialize();
    }

    /**
     * Initialise le client Twilio
     */
    private function initialize(): void
    {
        // Vérification que les variables d'environnement sont bien définies
        if ($this->accountSid === null || $this->authToken === null) {
            $this->logger->error('❌ ERREUR: Variables d\'environnement TWILIO manquantes!');
            $this->logger->error('Veuillez définir TWILIO_ACCOUNT_SID et TWILIO_AUTH_TOKEN dans votre fichier .env');
            return;
        }

        try {
            $this->twilioClient = new Client($this->accountSid, $this->authToken);
            $this->logger->info('✅ WhatsApp Service initialisé avec les variables d\'environnement');
            $this->logger->info('📱 Sandbox: envoyez \'join ' . self::SANDBOX_INVITE_CODE . '\' au ' . self::WHATSAPP_SANDBOX_NUMBER);
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur initialisation Twilio: ' . $e->getMessage());
        }
    }

    /**
     * Envoie un message de réinitialisation via WhatsApp
     */
    public function sendResetPasswordWhatsApp(string $toPhoneNumber, string $token): bool
    {
        try {
            // Vérifier que Twilio est initialisé
            if ($this->twilioClient === null) {
                $this->logger->error('❌ Impossible d\'envoyer: client Twilio non initialisé');
                return false;
            }

            $cleanNumber = $this->formatPhoneNumber($toPhoneNumber);
            
            if ($cleanNumber === null) {
                $this->logger->error('❌ Numéro de téléphone invalide: ' . $toPhoneNumber);
                return false;
            }

            // Construction du message
            $messageBody = $this->buildResetMessage($token);

            // Envoyer via WhatsApp
            $message = $this->twilioClient->messages->create(
                "whatsapp:" . $cleanNumber,
                [
                    'from' => "whatsapp:" . self::WHATSAPP_SANDBOX_NUMBER,
                    'body' => $messageBody
                ]
            );

            $this->logger->info('✅ Token envoyé sur WhatsApp !');
            $this->logger->info('🔑 Token: ' . $token);
            $this->logger->info('📱 Message SID: ' . $message->sid);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur envoi WhatsApp: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Construit le message de réinitialisation
     */
    private function buildResetMessage(string $token): string
    {
        return "🔐 *RÉINITIALISATION MOT DE PASSE EVENTFLOW*\n\n" .
               "Bonjour,\n\n" .
               "Voici votre code de réinitialisation :\n\n" .
               "📱 *TOKEN :* `" . $token . "`\n\n" .
               "📋 *INSTRUCTIONS :*\n" .
               "1. Ouvrez l'application EventFlow\n" .
               "2. Cliquez sur 'Mot de passe oublié'\n" .
               "3. Entrez votre numéro de téléphone\n" .
               "4. Sur l'écran suivant, collez ce token\n\n" .
               "⏱️ Ce token expirera dans *1 heure*\n\n" .
               "Merci,\n" .
               "L'équipe EventFlow";
    }

    /**
     * Formate le numéro de téléphone
     */
    private function formatPhoneNumber(?string $phoneNumber): ?string
    {
        if ($phoneNumber === null) {
            return null;
        }

        // Nettoyer: garder seulement les chiffres et le +
        $clean = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // Format tunisien: 8 chiffres -> ajouter +216
        if (preg_match('/^[0-9]{8}$/', $clean)) {
            $clean = '+216' . $clean;
        }
        // Format avec 216 sans + -> ajouter +
        elseif (preg_match('/^216[0-9]{8}$/', $clean)) {
            $clean = '+' . $clean;
        }
        // S'assurer que ça commence par +
        elseif (!str_starts_with($clean, '+')) {
            $clean = '+' . $clean;
        }

        return $clean;
    }

    /**
     * Vérifie si un numéro a rejoint le sandbox
     */
    public function isNumberJoinedSandbox(string $phoneNumber): bool
    {
        // TODO: Implémenter la vérification via Twilio Verify ou stockage local
        // Pour l'instant, on retourne true pour les tests
        $this->logger->info('🔍 Vérification sandbox pour: ' . $phoneNumber);
        return true;
    }

    /**
     * Méthode utilitaire pour tester la configuration
     */
    public function isConfigured(): bool
    {
        return $this->twilioClient !== null;
    }

    /**
     * Récupère les informations de configuration
     */
  /**
 * @return array{configured: bool, sandbox_number: string, invite_code: string, account_sid_defined: bool, auth_token_defined: bool}
 */
public function getConfigInfo(): array{
        return [
            'configured' => $this->isConfigured(),
            'sandbox_number' => self::WHATSAPP_SANDBOX_NUMBER,
            'invite_code' => self::SANDBOX_INVITE_CODE,
            'account_sid_defined' => !empty($this->accountSid),
            'auth_token_defined' => !empty($this->authToken)
        ];
    }

    /**
     * Teste la connexion Twilio
     */
    public function testConnection(): bool
    {
        try {
            if (!$this->twilioClient) {
                return false;
            }

            // Tenter de récupérer les informations du compte
            $account = $this->twilioClient->api->v2010->accounts($this->accountSid)->fetch();
            
            $this->logger->info('✅ Connexion Twilio réussie - Compte: ' . $account->friendlyName);
            return true;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur test connexion: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie une invitation à rejoindre le sandbox
     */
    public function sendSandboxInvitation(string $phoneNumber): bool
    {
        try {
            if (!$this->twilioClient) {
                return false;
            }

            $cleanNumber = $this->formatPhoneNumber($phoneNumber);
            
            if ($cleanNumber === null) {
                return false;
            }

            $inviteMessage = "Bienvenue sur EventFlow! Pour recevoir nos notifications WhatsApp, " .
                           "veuillez envoyer 'join " . self::SANDBOX_INVITE_CODE . "' au " .
                           self::WHATSAPP_SANDBOX_NUMBER;

            $this->twilioClient->messages->create(
                "whatsapp:" . $cleanNumber,
                [
                    'from' => "whatsapp:" . self::WHATSAPP_SANDBOX_NUMBER,
                    'body' => $inviteMessage
                ]
            );

            $this->logger->info('✅ Invitation sandbox envoyée à: ' . $cleanNumber);
            return true;

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur envoi invitation: ' . $e->getMessage());
            return false;
        }
    }
}