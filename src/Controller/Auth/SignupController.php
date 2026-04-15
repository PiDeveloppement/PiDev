<?php

namespace App\Controller\Auth;

use App\Entity\User\UserModel;
use App\Form\User\RegistrationType;
use App\Service\User\UserService;
use App\Service\User\EmailService;
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

    public function __construct(
        UserService $userService,
        EmailService $emailService,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->userService = $userService;
        $this->emailService = $emailService;
        $this->passwordHasher = $passwordHasher;
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

        $user->setRoleId(1); 
        
        $defaultRole = $this->userService->getRoleById(1);
        if ($defaultRole) {
            $user->setRole($defaultRole);
        }

        $success = $this->userService->createUser($user, null);

        if ($success) {
            // Envoyer l'email de bienvenue à l'utilisateur
            $this->emailService->sendWelcomeEmail($user);
            
            // Envoyer la notification aux admins
            $this->emailService->sendNewUserNotificationToAdmin($user);
            
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
    
    // VÉRIFIER SI UN AUTRE UTILISATEUR A DÉJÀ CE VISAGE
    $faceDescriptor = $data['descriptor'] ?? [];
    
    if (!empty($faceDescriptor)) {
        // Récupérer tous les utilisateurs qui ont un descripteur facial
        $usersWithFace = $em->getRepository(UserModel::class)
            ->createQueryBuilder('u')
            ->where('u.faceDescriptor IS NOT NULL')
            ->andWhere('u.id != :currentUserId')
            ->setParameter('currentUserId', $user->getId())
            ->getQuery()
            ->getResult();
        
        foreach ($usersWithFace as $existingUser) {
            $storedDescriptor = $existingUser->getFaceDescriptor();
            if ($storedDescriptor) {
                $similarity = $this->calculateSimilarity($faceDescriptor, $storedDescriptor);
                
                // Si la similarité est supérieure à 0.6 (seuil à ajuster)
                if ($similarity > 0.6) {
                    return $this->json([
                        'status' => 'error', 
                        'message' => 'Ce visage est déjà associé à un compte existant. Veuillez vous connecter.',
                        'redirect' => $this->generateUrl('app_login')
                    ], 409);
                }
            }
        }
    }
    
    // Sauvegarder le descripteur facial
    $user->setFaceDescriptor($data['descriptor']); 
    $em->flush();
    
    return $this->json(['status' => 'success']);
}

/**
 * Calcule la similarité entre deux descripteurs faciaux
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


}