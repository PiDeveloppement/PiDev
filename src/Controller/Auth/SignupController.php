<?php

namespace App\Controller\Auth;

use App\Entity\User\UserModel;
use App\Form\User\RegistrationType;
use App\Service\User\UserService;
use App\Service\User\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
            
            // Afficher les erreurs de validation
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('auth/signup.html.twig', [
            'registrationForm' => $form->createView()
        ]);
    }

    // src/Controller/Auth/SignupController.php

private function handleValidRegistration(UserModel $user, $form): Response
{
    // Vérifier si l'email existe déjà
    if ($this->userService->isEmailExists($user->getEmail())) {
        $this->addFlash('error', 'Cet email est déjà utilisé');
        return $this->redirectToRoute('app_register');
    }

    // Hasher le mot de passe
    $plainPassword = $form->get('plainPassword')->getData();
    $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
    $user->setPassword($hashedPassword);

    // Utiliser le rôle par défaut "Default" avec ID 1
    $user->setRoleId(1);  // Changé de 2 à 1
    
    // Optionnel: Récupérer aussi l'objet Role pour la relation
    $defaultRole = $this->userService->getRoleById(1);
    if ($defaultRole) {
        $user->setRole($defaultRole);
    }

    // Sauvegarder l'utilisateur
    $success = $this->userService->createUser($user, null); // Pas besoin de mot de passe ici car déjà hashé

    if ($success) {
        $this->addFlash('success', '✓ Inscription réussie !');
        return $this->redirectToRoute('app_login');
    }

    $this->addFlash('error', '✗ Échec de l\'inscription');
    return $this->redirectToRoute('app_register');
}

    #[Route('/check-email', name: 'app_check_email', methods: ['POST'])]
    public function checkEmail(Request $request): Response
    {
        $email = $request->request->get('email');
        
        if (!$email) {
            return $this->json(['exists' => false]);
        }

        $exists = $this->userService->isEmailExists($email);
        
        return $this->json([
            'exists' => $exists,
            'message' => $exists ? 'Email déjà utilisé' : 'Email disponible'
        ]);
    }

    #[Route('/validate-password', name: 'app_validate_password', methods: ['POST'])]
    public function validatePassword(Request $request): Response
    {
        $password = $request->request->get('password');
        
        if (!$password) {
            return $this->json(['valid' => false, 'message' => 'Mot de passe requis']);
        }

        $valid = $this->isValidPassword($password);
        $strength = $this->getPasswordStrength($password);

        return $this->json([
            'valid' => $valid,
            'strength' => $strength,
            'message' => $valid ? 'Mot de passe fort' : '6+ caractères avec lettre, chiffre ET symbole'
        ]);
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
        
        return match($score) {
            5 => 'Très fort',
            4 => 'Fort',
            3 => 'Moyen',
            default => 'Faible'
        };
    }
}