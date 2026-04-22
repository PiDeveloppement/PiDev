<?php

namespace App\Controller\Auth;

use App\Entity\User\UserModel;
use App\Entity\Event\Event;
use App\Service\User\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Cookie;

class LoginController extends AbstractController
{
    private UserService $userService;
    private EntityManagerInterface $em;

    public function __construct(
        UserService $userService,
        EntityManagerInterface $em,
    ) {
        $this->userService = $userService;
        $this->em = $em;
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $savedEmail = $request->cookies->get('saved_email');
        $savedPassword = $request->cookies->get('saved_password');
        $rememberMe = !empty($savedEmail) && !empty($savedPassword);
        $lastLogin = $request->cookies->get('last_login');
        $lastEmail = $request->cookies->get('last_email');

        // Récupérer l'ID de l'événement en attente depuis l'URL
        $pendingEventId = $request->query->get('pending_event');
        if ($pendingEventId) {
            $request->getSession()->set('pending_event', (int) $pendingEventId);
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername ?: $savedEmail,
            'saved_password' => $savedPassword,
            'remember_me' => $rememberMe,
            'error' => $error,
            'last_login' => $lastLogin,
            'last_email' => $lastEmail,
            'facial_login_available' => $this->checkOpenCvAvailability()
        ]);
    }

    #[Route('/login/check', name: 'app_login_check', methods: ['POST'])]
    public function loginCheck(Request $request): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $rememberMe = $request->request->get('_remember_me') === 'on';

        if (empty($email) || empty($password)) {
            $this->addFlash('error', 'Email et mot de passe requis');
            return $this->redirectToRoute('app_login');
        }

        try {
            $user = $this->userService->authenticate($email, $password);

            if (!$user) {
                $this->addFlash('error', 'Email ou mot de passe incorrect');
                return $this->redirectToRoute('app_login');
            }

            // Mettre à jour la date de dernière connexion
            if (method_exists($user, 'setLastLoginAt')) {
                $user->setLastLoginAt(new \DateTimeImmutable());
            }
            $this->em->flush();

            // Créer le token d'authentification
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->container->get('security.token_storage')->setToken($token);
            
            $session = $request->getSession();
            $session->set('_security_main', serialize($token));
            $session->save();

            // 🎯 Vérifier s'il y a une redirection vers le quiz en attente
            $afterLoginRedirect = $session->get('after_login_redirect');
            $pendingQuizEvent = $session->get('pending_quiz_event');
            $pendingEventId = $session->get('pending_event');

            if ($afterLoginRedirect === 'app_quiz_start' && $pendingQuizEvent) {
                $session->remove('after_login_redirect');
                $session->remove('pending_quiz_event');
                $response = $this->redirectToRoute('app_quiz_start', ['event_id' => $pendingQuizEvent]);
            } elseif ($pendingEventId) {
                $session->remove('after_login_redirect');
                $session->remove('pending_quiz_event');
                $response = $this->redirectToRoute('app_public_event_participation_confirm', ['id' => (int) $pendingEventId]);
            } else {
                // ✅ FORCER la redirection selon le rôle (ignorer toute redirection précédente)
                $redirectRoute = $this->resolveHomeRouteForUser($user);

                // ⚠️ Supprimer toute redirection stockée en session
                $session->remove('after_login_redirect');
                $session->remove('_security.main.target_path');
                $session->remove('pending_quiz_event');

                $response = $this->redirectToRoute($redirectRoute);
            }

            // Gérer "Se souvenir de moi"
            if ($rememberMe) {
                $response->headers->setCookie(Cookie::create('saved_email', $email, time() + 30*24*3600));
                $response->headers->setCookie(Cookie::create('saved_password', $password, time() + 30*24*3600));
                $lastLogin = (new \DateTime())->format('d/m/Y H:i:s');
                $response->headers->setCookie(Cookie::create('last_login', $lastLogin, time() + 30*24*3600));
                $response->headers->setCookie(Cookie::create('last_email', $email, time() + 30*24*3600));
                $this->addFlash('success', '✓ Identifiants sauvegardés');
            } else {
                $response->headers->clearCookie('saved_email');
                $response->headers->clearCookie('saved_password');
            }

            return $response;

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur de connexion: ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/login/facial', name: 'app_facial_login')]
    public function facialLogin(): Response
    {
        return $this->render('auth/facial_login.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    private function checkOpenCvAvailability(): bool
    {
        return class_exists('OpenCV\Core') || class_exists('CV\OpenCV');
    }

    /**
     * Détermine la route de redirection selon le roleId de l'utilisateur
     */
    private function resolveHomeRouteForUser(?UserModel $user): string
    {
        if (!$user instanceof UserModel) {
            return 'app_landing';
        }

        $roleId = $user->getRoleId();

        // Role id 4 (Sponsor) → portale_home
        if ($roleId == 4) {
            return 'app_sponsor_portal';
        }

        // Role id 3 (Organisateur) ou Role id 2 (Admin) → dashboard
        if ($roleId == 3 || $roleId == 2) {
            return 'app_dashboard';
        }

        // Role id 1 (Default) ou Role id 5 (Participant) → page événements publics
        if ($roleId == 1 || $roleId == 5) {
            return 'app_public_events';
        }

        // Redirection par défaut
        return 'app_public_events';
    }

    #[Route('/check-auth', name: 'app_check_auth')]
    public function checkAuth(): Response
    {
        if (!$this->getUser()) {
            return new Response('❌ Non authentifié');
        }
        
        $user = $this->getUser();
        $token = $this->container->get('security.token_storage')->getToken();
        
        $html = '<h1>État de l\'authentification</h1>';
        $html .= '<p>User: ' . $user->getUserIdentifier() . '</p>';
        $html .= '<p>Roles: ' . implode(', ', $user->getRoles()) . '</p>';
        $html .= '<p>Token: ' . ($token ? '✅ Présent' : '❌ Absent') . '</p>';
        
        if ($token) {
            $html .= '<p>Token authenticated: ' . ($token->isAuthenticated() ? '✅ Oui' : '❌ Non') . '</p>';
        }
        
        return new Response($html);
    }
}