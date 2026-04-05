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
   // private EventTicketService $ticketService;
   // private EventService $eventService;

    public function __construct(
        UserService $userService,
        EntityManagerInterface $em,
     //   EventTicketService $ticketService,
       // EventService $eventService
    ) {
        $this->userService = $userService;
        $this->em = $em;
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

        $this->handlePendingEventParticipation($session, $user);

        // Vérifier que le token est bien stocké
        if (!$session->has('_security_main')) {
            throw new \Exception('La session n\'a pas pu être sauvegardée');
        }

        $redirectRoute = $session->get('after_login_redirect', 'app_dashboard');
        $session->remove('after_login_redirect');

        // Créer la réponse
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
                ->andWhere('t.eventId = :eventId')
                ->andWhere('t.userId = :userId')
                ->setParameter('eventId', $event->getId())
                ->setParameter('userId', $user->getId())
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingTicket instanceof Ticket) {
                $this->addFlash('info', sprintf('Vous etes deja inscrit a "%s".', $event->getTitle()));
                return;
            }

            $ticketsForEvent = (int) $ticketRepo->count(['eventId' => $event->getId()]);
            if ((int) $event->getCapacity() > 0 && $ticketsForEvent >= (int) $event->getCapacity()) {
                $this->addFlash('warning', sprintf('Capacite atteinte pour "%s".', $event->getTitle()));
                return;
            }

            $ticket = new Ticket();
            $ticket->setEvent($event);
            $ticket->setUser($user);
            $ticket->setTicketCode(Ticket::generateTicketCode((int) $event->getId(), (int) $user->getId()));

            $this->em->persist($ticket);
            $this->em->flush();

            $this->addFlash('success', sprintf(
                'Bienvenue ! Votre billet pour "%s" a ete cree automatiquement. Code: %s',
                $event->getTitle(),
                $ticket->getTicketCode()
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Connexion reussie mais creation du billet impossible.');
        } finally {
            $session->remove('pending_event');
        }
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