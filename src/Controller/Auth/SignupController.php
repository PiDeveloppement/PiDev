<?php

namespace App\Controller\Auth;

use App\Entity\User\UserModel;
use App\Form\User\RegistrationType;
use App\Service\User\UserService;
use App\Service\User\EmailService;
use App\Service\Role\RoleAssignmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class SignupController extends AbstractController
{
    private UserService $userService;
    private EmailService $emailService;
    private UserPasswordHasherInterface $passwordHasher;
    private RoleAssignmentService $roleAssignmentService;

    public function __construct(
        UserService $userService,
        EmailService $emailService,
        UserPasswordHasherInterface $passwordHasher,
        RoleAssignmentService $roleAssignmentService
    ) {
        $this->userService = $userService;
        $this->emailService = $emailService;
        $this->passwordHasher = $passwordHasher;
        $this->roleAssignmentService = $roleAssignmentService;
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new UserModel();
        $user->setRegistrationDate(new \DateTime());
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                return $this->handleValidRegistration($user, $form);
            }
            
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('auth/signup.html.twig', [
            'registrationForm' => $form->createView(),
            'setup_face' => $request->query->get('setup_face'),
            'user_email' => $request->query->get('email')
        ]);
    }

    private function handleValidRegistration(UserModel $user, $form): Response
    {
        if ($this->userService->isEmailExists($user->getEmail())) {
            $this->addFlash('error', 'Cet email est déjà utilisé');
            return $this->redirectToRoute('app_register');
        }

        $plainPassword = $form->get('plainPassword')->getData();
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        // Assignation automatique du rôle basée sur le domaine de l'email
        $roleId = $this->roleAssignmentService->assignRoleByEmail($user->getEmail());
        $user->setRoleId($roleId);
        
        $role = $this->userService->getRoleById($roleId);
        if ($role) {
            $user->setRole($role);
        }

        $success = $this->userService->createUser($user, null);

        error_log('=== SignupController - Données utilisateur avant email ===');
        error_log('Email: ' . $user->getEmail());
        error_log('FirstName: ' . $user->getFirstName());
        error_log('LastName: ' . $user->getLastName());
        error_log('Faculté: ' . ($user->getFaculte() ?: 'NULL'));

        if ($success) {
            // L'email de bienvenue est envoyé dans UserService
            // Envoyer la notification aux admins (désactivé temporairement)
            // $this->emailService->sendNewUserNotificationToAdmin($user);

            $this->addFlash('success', '✓ Inscription réussie ! Étape finale : Configuration Face ID.');
            return $this->redirectToRoute('app_register', [
                'setup_face' => 1,
                'email' => $user->getEmail()
            ]);
        }

        $this->addFlash('error', '✗ Échec de l\'inscription');
        return $this->redirectToRoute('app_register');
    }

    #[Route('/check-email', name: 'app_check_email', methods: ['POST'])]
    public function checkEmail(Request $request): Response
    {
        $email = $request->request->get('email');
        if (!$email) return $this->json(['exists' => false]);
        $exists = $this->userService->isEmailExists($email);
        return $this->json(['exists' => $exists, 'message' => $exists ? 'Email déjà utilisé' : 'Email disponible']);
    }

    #[Route('/validate-password', name: 'app_validate_password', methods: ['POST'])]
    public function validatePassword(Request $request): Response
    {
        $password = $request->request->get('password');
        if (!$password) return $this->json(['valid' => false, 'message' => 'Mot de passe requis']);
        $valid = $this->isValidPassword($password);
        return $this->json(['valid' => $valid, 'strength' => $this->getPasswordStrength($password)]);
    }

    private function isValidPassword(string $password): bool
    {
        $regex = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?])[A-Za-z\d!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]{6,}$/';
        return (bool) preg_match($regex, $password);
    }

    private function getPasswordStrength(string $password): string
    {
        $score = 0;
        if (strlen($password) >= 8) $score++;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/\d/', $password)) $score++;
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) $score++;
        return match($score) { 5 => 'Très fort', 4 => 'Fort', 3 => 'Moyen', default => 'Faible' };
    }
    
#[Route('/save-face-descriptor', name: 'app_save_face_descriptor', methods: ['POST'])]
public function saveFaceDescriptor(Request $request, EntityManagerInterface $em): Response
{
    $data = json_decode($request->getContent(), true);
    $user = $em->getRepository(UserModel::class)->findOneBy(['email' => $data['email']]);

    if (!$user) {
        return $this->json(['status' => 'error', 'message' => 'Utilisateur non trouvé'], 404);
    }

    $faceDescriptor = $data['descriptor'] ?? [];

    if (!empty($faceDescriptor)) {
        $usersWithFace = $em->getRepository(UserModel::class)
            ->createQueryBuilder('u')
            ->where('u.faceDescriptor IS NOT NULL')
            ->andWhere('u.id != :currentUserId')
            ->setParameter('currentUserId', $user->getId())
            ->getQuery()
            ->getResult();

        foreach ($usersWithFace as $existingUser) {
            $storedDescriptor = $existingUser->getFaceDescriptor();
            if ($storedDescriptor && is_array($storedDescriptor)) {
                $distance = $this->calculateEuclideanDistance($faceDescriptor, $storedDescriptor);

                if ($distance < 0.6) {
                    // ✅ Supprimer le compte qui vient d'être créé
                    $em->remove($user);
                    $em->flush();

                    return $this->json([
                        'status' => 'error',
                        'message' => 'Ce visage est déjà associé à un compte existant. Votre inscription a été annulée.',
                        'redirect' => $this->generateUrl('app_login')
                    ], 409);
                }
            }
        }
    }

    $user->setFaceDescriptor($faceDescriptor);
    $em->flush();

    return $this->json(['status' => 'success']);
}

/**
 * Distance euclidienne — seuil face-api.js : < 0.6 = même personne
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




}