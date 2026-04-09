<?php

namespace App\Controller\Auth;

use App\Entity\User\UserModel;
use App\Entity\Event\Event;
use App\Entity\Event\Ticket;
use App\Service\User\UserService;
use Doctrine\ORM\EntityManagerInterface;
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
        // ✅ Si déjà connecté, rediriger directement selon le rôle
        if ($this->getUser()) {
            return $this->redirectToRoute($this->resolveHomeRouteForUser($this->getUser()));
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $savedEmail = $request->cookies->get('saved_email');
        $savedPassword = $request->cookies->get('saved_password');
        $rememberMe = !empty($savedEmail) && !empty($savedPassword);
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

    #[Route('/login/check', name: 'app_login_check', methods: ['POST'])]
    public function loginCheck(Request $request): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $rememberMe = $request->request->get('_remember_me') === 'on';

        if (empty($email) || empty($password)) {
            $this->addFlash('error', 'Email et mot de passe requis');
            return $this->redirectToRoute('app_facial_login');
        }

        try {
            $user = $this->userService->authenticate($email, $password);

            if (!$user) {
                $this->addFlash('error', 'Email ou mot de passe incorrect');
                return $this->redirectToRoute('app_facial_login');
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

            $this->handlePendingEventParticipation($session, $user);

            // ✅ FORCER la redirection selon le rôle (ignorer toute redirection précédente)
            $redirectRoute = $this->resolveHomeRouteForUser($user);
            
            // ⚠️ Supprimer toute redirection stockée en session
            $session->remove('after_login_redirect');
            $session->remove('_security.main.target_path');

            $response = $this->redirectToRoute($redirectRoute);

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
            return $this->redirectToRoute('app_facial_login');
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

        try {
            $event = $this->em->getRepository(Event::class)
                ->createQueryBuilder('e')
                ->andWhere('e.id = :id')
                ->andWhere('e.endDate >= :now')
                ->andWhere('e.status = :status')
                ->setParameter('id', (int) $pendingEventId)
                ->setParameter('now', new \DateTimeImmutable())
                ->setParameter('status', Event::STATUS_PUBLISHED)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$event instanceof Event) {
                return;
            }

            $ticketRepo = $this->em->getRepository(Ticket::class);
            $existingTicket = $ticketRepo->createQueryBuilder('t')
                ->andWhere('t.event = :event')
                ->andWhere('t.user = :user')
                ->setParameter('event', $event)
                ->setParameter('user', $user)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingTicket instanceof Ticket) {
                $this->addFlash('info', sprintf('Vous êtes déjà inscrit à "%s".', $event->getTitle()));
                return;
            }

            $ticketsForEvent = (int) $ticketRepo->count(['event' => $event]);
            if ((int) $event->getCapacity() > 0 && $ticketsForEvent >= (int) $event->getCapacity()) {
                $this->addFlash('warning', sprintf('Capacité atteinte pour "%s".', $event->getTitle()));
                return;
            }

            $ticket = new Ticket();
            $ticket->setEvent($event);
            $ticket->setUser($user);
            $ticket->setTicketCode(Ticket::generateTicketCode((int) $event->getId(), (int) $user->getId()));

            $this->em->persist($ticket);
            $this->em->flush();

            $this->addFlash('success', sprintf(
                'Bienvenue ! Votre billet pour "%s" a été créé automatiquement. Code: %s',
                $event->getTitle(),
                $ticket->getTicketCode()
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Connexion réussie mais création du billet impossible.');
        } finally {
            $session->remove('pending_event');
        }
    }

    /**
     * Détermine la route de redirection selon le rôle de l'utilisateur
     */
    private function resolveHomeRouteForUser(?UserModel $user): string
    {
        if (!$user instanceof UserModel) {
            return 'app_landing';
        }

        $roles = $user->getRoles();
        
        // ✅ Participant → mes billets
        if (in_array('ROLE_PARTICIPANT', $roles, true)) {
            return 'app_my_tickets';
        }
        
        // ✅ Sponsor → portal sponsor
        if (in_array('ROLE_SPONSOR', $roles, true)) {
            return 'app_sponsor_portal';
        }
        
        // ✅ Admin et Organisateur → dashboard
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_ORGANISATEUR', $roles, true)) {
            return 'app_dashboard';
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