<?php

namespace App\Controller\User;

use App\Entity\User\UserModel;
use App\Repository\Role\RoleRepository;
use App\Repository\User\UserRepository;
use App\Service\Role\RoleService;
use App\Service\User\UserService;
use App\Bundle\NotificationBundle\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/profile')]
class ProfilController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private RoleService $roleService,
       // private EventService $eventService,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private SluggerInterface $slugger,
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'app_profile')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof UserModel) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les statistiques
     //   $eventCount = $this->eventService->countUserEvents($user->getId());
        $roles = $this->roleRepository->findAllNames();

        return $this->render('user/profile.html.twig', [
            'user' => $user,
          //  'eventCount' => $eventCount,
            'roles' => $roles,
            'lastLogin' => new \DateTime(),
            'bioMaxLength' => 500
        ]);
    }

    #[Route('/update', name: 'app_profile_update', methods: ['POST'])]
    public function updateProfile(Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof UserModel) {
            return $this->redirectToRoute('app_login');
        }

        // Validation des champs requis
        if (empty($request->request->get('first_name')) || 
            empty($request->request->get('last_name')) || 
            empty($request->request->get('email'))) {
            $this->addFlash('error', '❌ Veuillez remplir tous les champs obligatoires');
            return $this->redirectToRoute('app_profile');
        }

        // Mise à jour des informations
        $user->setFirstName($request->request->get('first_name'));
        $user->setLastName($request->request->get('last_name'));
        $user->setEmail($request->request->get('email'));
        $user->setPhone($request->request->get('phone'));
        $user->setFaculte($request->request->get('faculty'));
        $user->setBio($request->request->get('bio'));

        // Gestion de l'upload d'image
        /** @var UploadedFile $imageFile */
        $imageFile = $request->files->get('profile_image');
        
        if ($imageFile) {
            $this->handleImageUpload($imageFile, $user);
        }

        $this->entityManager->flush();
        
        $this->addFlash('success', '✅ Profil mis à jour avec succès');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/change-password', name: 'app_profile_change_password', methods: ['POST'])]
    public function changePassword(Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof UserModel) {
            return $this->redirectToRoute('app_login');
        }

        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        // Validation du mot de passe actuel
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', '❌ Mot de passe actuel incorrect');
            return $this->redirectToRoute('app_profile');
        }

        // Validation des nouveaux mots de passe
        if (empty($newPassword) || empty($confirmPassword)) {
            $this->addFlash('error', '❌ Veuillez remplir tous les champs');
            return $this->redirectToRoute('app_profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', '❌ Les nouveaux mots de passe ne correspondent pas');
            return $this->redirectToRoute('app_profile');
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('error', '❌ Le mot de passe doit contenir au moins 6 caractères');
            return $this->redirectToRoute('app_profile');
        }

        // Mise à jour du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $this->entityManager->flush();

        // Envoyer notification SMS via NotificationService
        try {
            error_log('=== Changement de mot de passe - Vérification téléphone ===');
            error_log('Numéro de téléphone: ' . ($user->getPhone() ?: 'NULL'));
            
            if ($user->getPhone()) {
                error_log('Envoi SMS en cours vers: ' . $user->getPhone());
                $this->notificationService->sendSms(
                    $user->getPhone(),
                    'EventFlow: Votre mot de passe a été modifié avec succès. Si vous n\'avez pas effectué cette action, contactez le support.'
                );
                error_log('SMS envoyé avec succès');
            } else {
                error_log('Aucun numéro de téléphone, SMS non envoyé');
            }
        } catch (\Exception $e) {
            error_log('Erreur envoi SMS: ' . $e->getMessage());
        }

        $this->addFlash('success', '✅ Mot de passe changé avec succès');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/upload-image', name: 'app_profile_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof UserModel) {
            return $this->json(['error' => 'Non authentifié'], 403);
        }

        /** @var UploadedFile $imageFile */
        $imageFile = $request->files->get('image');

        if (!$imageFile) {
            return $this->json(['error' => 'Aucune image fournie'], 400);
        }

        if ($imageFile->getSize() > 5 * 1024 * 1024) {
            return $this->json(['error' => 'La taille maximale est de 5MB'], 400);
        }

        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        try {
            $imageFile->move(
                $this->getParameter('profile_images_directory'),
                $newFilename
            );

            $user->setProfilePictureUrl('/uploads/profile_images/' . $newFilename);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'url' => $user->getProfilePictureUrl()
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function handleImageUpload(UploadedFile $file, UserModel $user): void
    {
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \Exception('La taille maximale est de 5MB');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $file->move(
            $this->getParameter('profile_images_directory'),
            $newFilename
        );

        $user->setProfilePictureUrl('/uploads/profile_images/' . $newFilename);
    }
    #[Route('/verify-password', name: 'app_profile_verify_password', methods: ['POST'])]
public function verifyPassword(Request $request): JsonResponse
{
    $user = $this->getUser();
    
    if (!$user instanceof UserModel) {
        return $this->json(['valid' => false], 403);
    }

    $password = $request->request->get('password');
    $isValid = $this->passwordHasher->isPasswordValid($user, $password);

    return $this->json(['valid' => $isValid]);
}
}