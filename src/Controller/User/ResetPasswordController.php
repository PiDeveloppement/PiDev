<?php

namespace App\Controller\User;

use App\Service\User\PasswordResetService;
use App\Service\User\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ResetPasswordController extends AbstractController
{
    private ?string $currentToken = null;

    public function __construct(
        private PasswordResetService $resetService,
        private UserService $userService
    ) {}

    #[Route('/reset-password', name: 'app_reset_password')]
    public function index(Request $request): Response
    {
        $token = $request->query->get('token', '');

        return $this->render('user/reset_password.html.twig', [
            'token' => $token,
            'step' => $token ? 'reset' : 'validate'
        ]);
    }

    #[Route('/reset-password/validate', name: 'app_reset_password_validate', methods: ['POST'])]
    public function validateToken(Request $request): Response
    {
        $token = $request->request->get('token');

        if (empty($token)) {
            $this->addFlash('error', '❌ Veuillez saisir le token reçu par WhatsApp');
            return $this->redirectToRoute('app_reset_password');
        }

        $isValid = $this->resetService->validateToken($token);

        if ($isValid) {
            $this->currentToken = $token;
            return $this->render('user/reset_password.html.twig', [
                'token' => $token,
                'step' => 'reset',
                'valid' => true
            ]);
        }

        $this->addFlash('error', '❌ Token invalide ou expiré. Demandez un nouveau lien.');
        return $this->redirectToRoute('app_reset_password');
    }

    #[Route('/reset-password/reset', name: 'app_reset_password_reset', methods: ['POST'])]
    public function resetPassword(Request $request): Response
    {
        $token = $request->request->get('token');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        // Validation
        if (empty($newPassword) || empty($confirmPassword)) {
            $this->addFlash('error', '❌ Veuillez remplir tous les champs');
            return $this->redirectToRoute('app_reset_password', ['token' => $token]);
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', '❌ Les mots de passe ne correspondent pas');
            return $this->redirectToRoute('app_reset_password', ['token' => $token]);
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('error', '❌ Le mot de passe doit contenir au moins 6 caractères');
            return $this->redirectToRoute('app_reset_password', ['token' => $token]);
        }

        // Valider le token
        if (!$this->resetService->validateToken($token)) {
            $this->addFlash('error', '❌ Token invalide ou expiré');
            return $this->redirectToRoute('app_reset_password');
        }

        // Réinitialiser le mot de passe
        $success = $this->resetService->resetPassword($newPassword);

        if ($success) {
            $this->addFlash('success', '✅ Mot de passe réinitialisé avec succès !');
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('error', '❌ Erreur lors de la réinitialisation');
        return $this->redirectToRoute('app_reset_password', ['token' => $token]);
    }

    public function setToken(?string $token): self
    {
        $this->currentToken = $token;
        return $this;
    }
}