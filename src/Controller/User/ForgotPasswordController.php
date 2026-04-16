<?php
// src/Controller/User/ForgotPasswordController.php

namespace App\Controller\User;

use App\Service\User\WhatsAppService;
use App\Service\User\PasswordResetService;
use App\Service\User\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private PasswordResetService $resetService,
        private UserService $userService
    ) {}

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function index(): Response
    {
        return $this->render('user/forgot_password.html.twig');
    }

    #[Route('/forgot-password/send', name: 'app_forgot_password_send', methods: ['POST'])]
    public function sendResetLink(Request $request): Response
    {
        $phoneNumber = $request->request->get('phone');

        if (empty($phoneNumber)) {
            $this->addFlash('error', '❌ Veuillez saisir votre numéro de téléphone');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Nettoyer le numéro
        $cleanPhone = $this->cleanPhoneNumber($phoneNumber);

        try {
            // Vérifier la configuration Twilio
            if (!$this->whatsAppService->isConfigured()) {
                $this->addFlash('error', '❌ Twilio non configuré. Vérifiez TWILIO_ACCOUNT_SID et TWILIO_AUTH_TOKEN dans .env');
                return $this->redirectToRoute('app_forgot_password');
            }

            // Récupérer l'utilisateur par téléphone
            $user = $this->userService->getUserByPhone($cleanPhone);

            if (!$user) {
                $this->addFlash('error', '❌ Aucun compte avec ce numéro: ' . $cleanPhone);
                return $this->redirectToRoute('app_forgot_password');
            }

            // Créer un token
            $token = $this->resetService->createToken($user);

            // Envoyer le token via WhatsApp
            $sent = $this->whatsAppService->sendResetPasswordWhatsApp(
                $user->getPhone(),
                $token->getToken()
            );

            if ($sent) {
                $this->addFlash('success', '✅ Code de réinitialisation envoyé sur WhatsApp !');
                $this->addFlash('info', '📱 Token: ' . $token->getToken() . ' (pour test)');
                $this->addFlash('info', '📱 Si vous ne recevez rien, envoyez "join orange-popsicle" au +14155238886 sur WhatsApp');
                
                // Stocker le token en session pour la prochaine étape
                $request->getSession()->set('reset_token', $token->getToken());
                
                // Rediriger vers la page de réinitialisation avec le token
                return $this->redirectToRoute('app_reset_password', [
                    'token' => $token->getToken()
                ]);
            } else {
                $this->addFlash('error', '❌ Erreur lors de l\'envoi WhatsApp. Vérifiez que vous avez rejoint le sandbox.');
                $this->addFlash('info', '📱 Token généré: ' . $token->getToken() . ' (utilisez-le directement)');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur: ' . $e->getMessage());
            $this->addFlash('error', '❌ Stack trace: ' . $e->getTraceAsString());
        }

        return $this->redirectToRoute('app_forgot_password');
    }

    #[Route('/reset-password/verify/{token}', name: 'app_reset_password_verify')]
    public function verifyTokenPage(string $token): Response
    {
        // Vérifier si le token est valide
        $isValid = $this->resetService->validateToken($token);
        
        if (!$isValid) {
            $this->addFlash('error', '❌ Token invalide ou expiré. Veuillez demander un nouveau code.');
            return $this->redirectToRoute('app_forgot_password');
        }
        
        return $this->render('security/reset_password_verify.html.twig', [
            'token' => $token
        ]);
    }

    #[Route('/reset-password/validate-code', name: 'app_reset_password_validate_code', methods: ['POST'])]
    public function validateCode(Request $request): Response
    {
        $token = $request->request->get('token');
        $userCode = $request->request->get('code');
        
        if (empty($token) || empty($userCode)) {
            $this->addFlash('error', '❌ Veuillez saisir le code reçu par WhatsApp');
            return $this->redirectToRoute('app_forgot_password');
        }
        
        // Récupérer le token depuis la base
        $resetToken = $this->resetService->getTokenEntity($token);
        
        if (!$resetToken || !$resetToken->isValid()) {
            $this->addFlash('error', '❌ Token invalide ou expiré');
            return $this->redirectToRoute('app_forgot_password');
        }
        
        // Vérifier si le code correspond (dans notre cas, le token est le code)
        // On compare le token saisi avec le token stocké
        if ($userCode !== $token) {
            $this->addFlash('error', '❌ Code incorrect. Veuillez réessayer.');
            return $this->redirectToRoute('app_reset_password_verify', ['token' => $token]);
        }
        
        // Code valide, rediriger vers le formulaire de changement de mot de passe
        return $this->redirectToRoute('app_reset_password_form', ['token' => $token]);
    }

    #[Route('/reset-password/form/{token}', name: 'app_reset_password_form')]
    public function resetPasswordForm(string $token): Response
    {
        $isValid = $this->resetService->validateToken($token);
        
        if (!$isValid) {
            $this->addFlash('error', '❌ Token invalide ou expiré');
            return $this->redirectToRoute('app_forgot_password');
        }
        
        return $this->render('security/reset_password_form.html.twig', [
            'token' => $token
        ]);
    }

    #[Route('/reset-password/submit', name: 'app_reset_password_submit', methods: ['POST'])]
    public function resetPassword(Request $request): Response
    {
        $token = $request->request->get('token');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        // Validation
        if (empty($newPassword) || empty($confirmPassword)) {
            $this->addFlash('error', '❌ Veuillez remplir tous les champs');
            return $this->redirectToRoute('app_reset_password_form', ['token' => $token]);
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', '❌ Les mots de passe ne correspondent pas');
            return $this->redirectToRoute('app_reset_password_form', ['token' => $token]);
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('error', '❌ Le mot de passe doit contenir au moins 6 caractères');
            return $this->redirectToRoute('app_reset_password_form', ['token' => $token]);
        }

        // Valider le token
        if (!$this->resetService->validateToken($token)) {
            $this->addFlash('error', '❌ Token invalide ou expiré');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Définir le token courant dans le service
        $this->resetService->setCurrentToken($token);
        
        // Récupérer l'ID utilisateur
        $userId = $this->resetService->getUserIdFromToken($token);
        $this->resetService->setCurrentUserId($userId);

        // Réinitialiser le mot de passe
        $success = $this->resetService->resetPassword($newPassword);

        if ($success) {
            $this->addFlash('success', '✅ Mot de passe réinitialisé avec succès !');
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('error', '❌ Erreur lors de la réinitialisation');
        return $this->redirectToRoute('app_reset_password_form', ['token' => $token]);
    }

    #[Route('/forgot-password/test-token', name: 'app_forgot_password_test', methods: ['POST'])]
    public function testToken(Request $request): Response
    {
        $lastToken = $request->getSession()->get('last_token');
        
        if ($lastToken) {
            return $this->redirectToRoute('app_reset_password_verify', ['token' => $lastToken]);
        }

        $this->addFlash('error', '❌ Aucun lien récent à tester');
        return $this->redirectToRoute('app_forgot_password');
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function resetPasswordPage(Request $request): Response
    {
        $token = $request->query->get('token', '');
        return $this->render('user/reset_password.html.twig', [
            'token' => $token
        ]);
    }

    #[Route('/reset-password/validate-token', name: 'app_reset_password_validate_token', methods: ['POST'])]
    public function validateTokenApi(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? '';

        if (empty($token)) {
            return new JsonResponse(['valid' => false, 'message' => 'Token vide']);
        }

        $isValid = $this->resetService->validateToken($token);

        return new JsonResponse(['valid' => $isValid]);
    }

    #[Route('/reset-password/reset', name: 'app_reset_password_reset_api', methods: ['POST'])]
    public function resetPasswordApi(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? '';
        $newPassword = $data['newPassword'] ?? '';
        $confirmPassword = $data['confirmPassword'] ?? '';

        // Validation
        if (empty($newPassword) || empty($confirmPassword)) {
            return new JsonResponse(['success' => false, 'message' => 'Veuillez remplir tous les champs']);
        }

        if ($newPassword !== $confirmPassword) {
            return new JsonResponse(['success' => false, 'message' => 'Les mots de passe ne correspondent pas']);
        }

        if (strlen($newPassword) < 6) {
            return new JsonResponse(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères']);
        }

        // Valider le token
        if (!$this->resetService->validateToken($token)) {
            return new JsonResponse(['success' => false, 'message' => 'Token invalide ou expiré']);
        }

        // Définir le token courant dans le service
        $this->resetService->setCurrentToken($token);
        
        // Récupérer l'ID utilisateur
        $userId = $this->resetService->getUserIdFromToken($token);
        $this->resetService->setCurrentUserId($userId);

        // Réinitialiser le mot de passe
        $success = $this->resetService->resetPassword($newPassword);

        if ($success) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la réinitialisation']);
    }

    private function cleanPhoneNumber(string $phone): string
    {
        // Enlever les espaces, tirets, etc.
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Si commence par 0 et 10 chiffres (France)
        if (preg_match('/^0[0-9]{9}$/', $phone)) {
            $phone = '+33' . substr($phone, 1);
        }
        
        // Si commence par 216 sans + (Tunisie)
        if (preg_match('/^216[0-9]{8}$/', $phone)) {
            $phone = '+' . $phone;
        }
        
        // Si 8 chiffres (Tunisie sans indicatif)
        if (preg_match('/^[0-9]{8}$/', $phone)) {
            $phone = '+216' . $phone;
        }
        
        return $phone;
    }
    
}