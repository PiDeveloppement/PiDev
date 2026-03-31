<?php

namespace App\Controller\Auth;

use App\Entity\User\UserModel;
use App\Service\User\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LoginController extends AbstractController
{
    private UserService $userService;
   // private EventTicketService $ticketService;
   // private EventService $eventService;

    public function __construct(
        UserService $userService,
     //   EventTicketService $ticketService,
       // EventService $eventService
    ) {
        $this->userService = $userService;
      //  $this->ticketService = $ticketService;
       // $this->eventService = $eventService;
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // Charger les identifiants sauvegardés depuis les cookies
        $savedEmail = $request->cookies->get('saved_email');
        $savedPassword = $request->cookies->get('saved_password');
        $rememberMe = !empty($savedEmail) && !empty($savedPassword);

        // Afficher la dernière connexion
        $lastLogin = $request->cookies->get('last_login');
        $lastEmail = $request->cookies->get('last_email');

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
// src/Controller/Auth/LoginController.php

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

        // 🔥 Vérifier que l'utilisateur a des rôles
        $roles = $user->getRoles();
        
        // ⚠️ Si $roles est vide, ajouter ROLE_USER
        if (empty($roles)) {
            $roles = ['ROLE_USER'];
        }

        // Créer le token avec les rôles
        $token = new UsernamePasswordToken($user, 'main', $roles);
        
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->container->get('security.token_storage');
        $tokenStorage->setToken($token);
        
        // Sauvegarder dans la session
        $session = $request->getSession();
        $session->set('_security_main', serialize($token));
        $session->save();

        // Vérifier que le token est bien stocké
        if (!$session->has('_security_main')) {
            throw new \Exception('La session n\'a pas pu être sauvegardée');
        }

        // Créer la réponse
        $response = $this->redirectToRoute('app_dashboard');

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

    private function handlePendingEventParticipation(SessionInterface $session, UserModel $user): void
    {
        $pendingEventId = $session->get('pending_event');
        
        if (!$pendingEventId) {
            return;
        }

       // try {
           // $event = $this->eventService->getEventById($pendingEventId);

          //  if (!$event) {
          //      $session->remove('pending_event');
           //     return;
            //}

         //   $ticket = $this->ticketService->createTicket($pendingEventId, $user->getId());

         //   if ($ticket) {
            //    $this->addFlash('success', sprintf(
           //         'Bienvenue ! Votre participation à "%s" est enregistrée. Code: %s',
                //    $event->getTitle(),
                //    $ticket->getTicketCode()
               // ));
         //   } else {
          //      $this->addFlash('warning', 'Connexion réussie mais impossible de créer votre ticket. Veuillez réessayer.');
           // }

       // } catch (\Exception $e) {
        //    $this->addFlash('error', 'Erreur lors de la participation différée: ' . $e->getMessage());
       // } finally {
         //   $session->remove('pending_event');
       //}
    }
    // src/Controller/Auth/LoginController.php

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