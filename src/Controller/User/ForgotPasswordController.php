<?php
// src/Controller/user/ForgotPasswordController.php

namespace App\Controller\User;


use App\Service\User\WhatsAppService;
use App\Service\User\PasswordResetService;
use App\Service\User\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        return $this->render('user/forgot_password.html.twig', [
            'whatsapp_sandbox' => [
                'number' => '+14155238886',
                'code' => 'orange-popsicle'
            ]
        ]);
    }

    #[Route('/forgot-password/send', name: 'app_forgot_password_send', methods: ['POST'])]
    public function sendResetLink(Request $request): Response
    {
        $phoneNumber = $request->request->get('phone');

        if (empty($phoneNumber)) {
            $this->addFlash('error', '❌ Veuillez saisir votre numéro de téléphone');
            return $this->redirectToRoute('app_forgot_password');
        }

        if (!preg_match('/^\+?[0-9]{8,15}$/', $phoneNumber)) {
            $this->addFlash('error', '❌ Format de numéro invalide (ex: +21692500441)');
            return $this->redirectToRoute('app_forgot_password');
        }

        try {
            // ✅ Récupérer l'utilisateur par téléphone (retourne UserModel)
            $user = $this->userService->getUserByPhone($phoneNumber);

            if (!$user) {
                $this->addFlash('error', '❌ Aucun compte avec ce numéro');
                return $this->redirectToRoute('app_forgot_password');
            }

            // ✅ Créer un token (retourne PasswordResetToken)
            $token = $this->resetService->createToken($user);  // ← ICI : UserModel est attendu

            // ✅ Envoyer le message WhatsApp
            $sent = $this->whatsAppService->sendResetPasswordWhatsApp(
                $user->getPhone(),
                $token->getToken()  // ← On utilise le token string
            );

            if ($sent) {
                $this->addFlash('success', '✅ Message envoyé sur WhatsApp !');
                $this->addFlash('info', '📱 N\'oubliez pas de rejoindre le sandbox WhatsApp: envoyez "join orange-popsicle" au +14155238886');
                
                // Rediriger vers la page de reset avec le token
                return $this->redirectToRoute('app_reset_password', [
                    'token' => $token->getToken()
                ]);
            } else {
                $this->addFlash('error', '❌ Erreur lors de l\'envoi WhatsApp');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_forgot_password');
    }

    #[Route('/forgot-password/test-token', name: 'app_forgot_password_test', methods: ['POST'])]
    public function testToken(Request $request): Response
    {
        $lastToken = $request->getSession()->get('last_token');
        
        if ($lastToken) {
            return $this->redirectToRoute('app_reset_password', ['token' => $lastToken]);
        }

        $this->addFlash('error', '❌ Aucun lien récent à tester');
        return $this->redirectToRoute('app_forgot_password');
    }
}