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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

class LoginController extends AbstractController
{
    private UserService $userService;
    private EntityManagerInterface $em;

    public function __construct(
        UserService $userService,
        EntityManagerInterface $em,
        private TexterInterface $texter
    ) {
        $this->userService = $userService;
        $this->em = $em;
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // Déconnecter l'utilisateur s'il est déjà connecté
        if ($this->getUser()) {
            $this->container->get('security.token_storage')->setToken(null);
            $session = $request->getSession();
            $session->invalidate();
            $session->clear();
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $savedEmail = $request->cookies->get('saved_email');
        $savedPassword = $request->cookies->get('saved_password');
        $rememberMe = !empty($savedEmail) && !empty($savedPassword);
        $lastLogin = $request->cookies->get('last_login');
        $lastEmail = $request->cookies->get('last_email');

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
            'facial_login_available' => true
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

            if (method_exists($user, 'setLastLoginAt')) {
                $user->setLastLoginAt(new \DateTimeImmutable());
            }
            $this->em->flush();

            // Envoyer notification SMS via Infobip lors du login
            if ($user->getPhone()) {
                try {
                    $sms = new SmsMessage(
                        $user->getPhone(),
                        'Nouvelle connexion détectée sur votre compte. Si vous n\'êtes pas à l\'origine de cette action, contactez le support immédiatement.'
                    );
                    $this->texter->send($sms);
                } catch (\Exception $e) {
                    // Log l'erreur mais ne pas bloquer l'action
                    error_log('Erreur envoi SMS: ' . $e->getMessage());
                }
            }

            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->container->get('security.token_storage')->setToken($token);
            
            $session = $request->getSession();
            $session->set('_security_main', serialize($token));
            $session->save();

            $this->handlePendingEventParticipation($session, $user);

            $afterLoginRedirect = $session->get('after_login_redirect');
            $pendingQuizEvent = $session->get('pending_quiz_event');

            if ($afterLoginRedirect === 'app_quiz_start' && $pendingQuizEvent) {
                $session->remove('after_login_redirect');
                $session->remove('pending_quiz_event');
                $response = $this->redirectToRoute('app_quiz_start', ['event_id' => $pendingQuizEvent]);
            } else {
                $redirectRoute = $this->resolveHomeRouteForUser($user);
                $session->remove('after_login_redirect');
                $session->remove('_security.main.target_path');
                $session->remove('pending_quiz_event');
                $response = $this->redirectToRoute($redirectRoute);
            }

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
    #[Route('/login/verify-face', name: 'app_verify_face', methods: ['POST'])]
    public function verifyFace(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $faceDescriptor = $data['descriptor'] ?? null;

    if (!$faceDescriptor || !is_array($faceDescriptor)) {
        return $this->json([
            'success' => false,
            'message' => 'Descripteur facial non fourni ou invalide'
        ], 400);
    }

    $users = $this->em->getRepository(UserModel::class)
        ->createQueryBuilder('u')
        ->where('u.faceDescriptor IS NOT NULL')
        ->getQuery()
        ->getResult();

    if (empty($users)) {
        return $this->json([
            'success' => false,
            'message' => 'Aucun utilisateur avec visage enregistré'
        ], 401);
    }

    $bestMatch = null;
    $bestDistance = PHP_FLOAT_MAX; // ✅ On cherche la distance MINIMALE
    $threshold = 0.6; // ✅ Seuil euclidien : < 0.6 = même personne

    foreach ($users as $user) {
        $userDescriptor = $user->getFaceDescriptor();
        if ($userDescriptor && is_array($userDescriptor) && count($userDescriptor) > 0) {
            $distance = $this->calculateEuclideanDistance($faceDescriptor, $userDescriptor);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestMatch = $user;
            }
        }
    }

    // ✅ Refusé si distance >= seuil (trop différent)
    if (!$bestMatch || $bestDistance >= $threshold) {
        return $this->json([
            'success' => false,
            'message' => 'Visage non reconnu. Aucune correspondance trouvée.',
            'distance' => round($bestDistance, 4),
            'threshold' => $threshold
        ], 401);
    }

    try {
        $user = $bestMatch;

        $session = $request->getSession();
        $session->migrate(true);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->container->get('security.token_storage')->setToken($token);
        $session->set('_security_main', serialize($token));
        $session->save();

        if (method_exists($user, 'setLastLoginAt')) {
            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        // Envoyer notification SMS via Infobip lors du login facial
        if ($user->getPhone()) {
            try {
                $sms = new SmsMessage(
                    $user->getPhone(),
                    'Nouvelle connexion détectée sur votre compte (reconnaissance faciale). Si vous n\'êtes pas à l\'origine de cette action, contactez le support immédiatement.'
                );
                $this->texter->send($sms);
            } catch (\Exception $e) {
                // Log l'erreur mais ne pas bloquer l'action
                error_log('Erreur envoi SMS: ' . $e->getMessage());
            }
        }

        $redirectRoute = $this->resolveHomeRouteForUser($user);
        $redirectUrl = $this->generateUrl($redirectRoute);

        return $this->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'redirect' => $redirectUrl,
            'user' => [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'roleId' => $user->getRoleId()
            ],
            'distance' => round($bestDistance, 4)
        ]);

    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Erreur lors de la connexion: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * ✅ Distance euclidienne — métrique officielle de face-api.js
 * Seuil : < 0.6 = même personne, >= 0.6 = personnes différentes
 */
private function calculateEuclideanDistance(array $descriptor1, array $descriptor2): float
{
    if (count($descriptor1) !== count($descriptor2)) {
        return PHP_FLOAT_MAX;
    }

    $sumSquares = 0;
    for ($i = 0; $i < count($descriptor1); $i++) {
        $diff = $descriptor1[$i] - $descriptor2[$i];
        $sumSquares += $diff * $diff;
    }

    return sqrt($sumSquares);
}

    
    /**
     * Calcule la similarité entre deux descripteurs faciaux
     * Utilise la similarité cosinus
     */
    private function calculateSimilarity(array $descriptor1, array $descriptor2): float
    {
        if (count($descriptor1) !== count($descriptor2)) {
            return 0;
        }
        
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ($i = 0; $i < count($descriptor1); $i++) {
            $dotProduct += $descriptor1[$i] * $descriptor2[$i];
            $norm1 += $descriptor1[$i] * $descriptor1[$i];
            $norm2 += $descriptor2[$i] * $descriptor2[$i];
        }
        
        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }
        
        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
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

private function resolveHomeRouteForUser(?UserModel $user): string
{
    if (!$user instanceof UserModel) {
        return 'app_landing';
    }

    $roleId = $user->getRoleId();

    if ($roleId == 4) {
        return 'app_sponsor_portal';
    }

    if ($roleId == 3 || $roleId == 2) {
        return 'app_dashboard';
    }

    return 'app_participation_confirm';
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
