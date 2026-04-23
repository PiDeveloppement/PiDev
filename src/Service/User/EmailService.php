<?php

namespace App\Service\User;
             
use App\Entity\User\UserModel;
use App\Repository\User\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class EmailService
{
    // Configuration SMTP pour Gmail
    private const SMTP_HOST = 'smtp.gmail.com';
    private const SMTP_PORT = 587;
    private const FROM_EMAIL = 'sellamiarij7@gmail.com';
    private const FROM_NAME = 'EventFlow';

    public function __construct(
        private MailerInterface $mailer,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private Environment $twig
    ) {
        $this->logger->info('📧 Service d\'email initialisé');
    }

    /**
     * Envoie un email de bienvenue après inscription
     */
    public function sendWelcomeEmail(UserModel $user): bool
    {
        try {
            $subject = '🎉 Bienvenue sur EventFlow !';

            $this->logger->info('📧 Tentative d\'envoi email bienvenue à: ' . $user->getEmail());

            // Générer le contenu HTML depuis un template Twig
            $htmlContent = $this->twig->render('email/welcome.html.twig', [
                'user' => $user
            ]);

            $email = (new Email())
                ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
                ->to($user->getEmail())
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('✅ Email de bienvenue envoyé avec succès à: ' . $user->getEmail());
            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('❌ Erreur transport envoi email bienvenue: ' . $e->getMessage());
            $this->logger->error('❌ Détails: ' . $e->getTraceAsString());
            return false;
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur inattendue envoi email bienvenue: ' . $e->getMessage());
            $this->logger->error('❌ Détails: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Envoie une notification à tous les admins pour chaque nouvelle inscription
     */
    public function sendNewUserNotificationToAdmin(UserModel $newUser): bool
    {
        try {
            // Récupérer tous les emails des admins
            $adminEmails = $this->userRepository->findAdminEmails();

            if (empty($adminEmails)) {
                $this->logger->warning('⚠️ Aucun admin trouvé pour notifier');
                return false;
            }

            $subject = '📢 Nouvel utilisateur inscrit sur EventFlow';

            // Statistiques pour le template
            $stats = [
                'total_users' => $this->userRepository->countAll(),
                'new_users_this_month' => $this->userRepository->countNewThisMonth(),
            ];

            // Générer le contenu HTML
            $htmlContent = $this->twig->render('email/admin_notification.html.twig', [
                'user' => $newUser,
                'stats' => $stats
            ]);

            $email = (new Email())
                ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
                ->subject($subject)
                ->html($htmlContent);

            // Ajouter tous les admins en destinataires
            foreach ($adminEmails as $adminEmail) {
                $email->addTo($adminEmail);
            }

            $this->mailer->send($email);

            $this->logger->info('✅ Notification envoyée à ' . count($adminEmails) . ' admin(s) pour: ' . $newUser->getEmail());
            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('❌ Erreur envoi notification admin: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur inattendue: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie un email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail(UserModel $user, string $resetToken): bool
    {
        try {
            $subject = '🔐 Réinitialisation de votre mot de passe EventFlow';

            $htmlContent = $this->twig->render('email/reset_password.html.twig', [
                'user' => $user,
                'resetToken' => $resetToken,
                'expirationHours' => 1
            ]);

            $email = (new Email())
                ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
                ->to($user->getEmail())
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('✅ Email de réinitialisation envoyé à: ' . $user->getEmail());
            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('❌ Erreur envoi reset password: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie un email de confirmation de participation
     */
    public function sendParticipationConfirmation(UserModel
     $user, array $ticketData): bool
    {
        try {
            $subject = '🎫 Confirmation de participation - ' . $ticketData['eventTitle'];

            $htmlContent = $this->twig->render('email/participation_confirmation.html.twig', [
                'user' => $user,
                'ticket' => $ticketData
            ]);

            $email = (new Email())
                ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
                ->to($user->getEmail())
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('✅ Confirmation de participation envoyée à: ' . $user->getEmail());
            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('❌ Erreur envoi confirmation participation: ' . $e->getMessage());
            return false;
        }
    }
}