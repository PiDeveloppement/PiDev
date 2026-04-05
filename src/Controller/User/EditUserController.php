<?php

namespace App\Controller\User;

use App\Entity\User\UserModel;
use App\Repository\Role\RoleRepository;
use App\Repository\User\UserRepository;
use App\Service\User\UserService;


use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class EditUserController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/{id}/edit', name: 'app_user_edit')]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->userRepository->findByIdWithRole($id);
        
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé');
            return $this->redirectToRoute('app_user_index');
        }

        $roles = $this->roleRepository->findAllNames();

        if ($request->isMethod('POST')) {
            return $this->handleEdit($request, $user);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'roles' => $roles,
            'userInfo' => $user->getFullName()
        ]);
    }

    private function handleEdit(Request $request, UserModel $user): Response
    {
        // Validation des champs
        if (!$this->validateFields($request)) {
            return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
        }

        $firstName = $request->request->get('first_name');
        $lastName = $request->request->get('last_name');
        $email = $request->request->get('email');
        $faculte = $request->request->get('faculte');
        $roleName = $request->request->get('role');

        // Mise à jour des informations
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setEmail($email);
        $user->setFaculte($faculte);

        // Gestion du rôle
        if ($roleName) {
            $role = $this->roleRepository->findOneByName($roleName);
            if ($role) {
                $user->setRole($role);
                $user->setRoleId($role->getId());
            } else {
                $this->addFlash('error', 'Rôle invalide');
                return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
            }
        }

        try {
            $this->entityManager->flush();
            $this->addFlash('success', '✅ Utilisateur mis à jour avec succès');
            
            // Callback vers la liste
            return $this->redirectToRoute('app_user_index');
            
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Échec de la mise à jour: ' . $e->getMessage());
            return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
        }
    }

    private function validateFields(Request $request): bool
    {
        $firstName = $request->request->get('first_name');
        $lastName = $request->request->get('last_name');
        $email = $request->request->get('email');
        $faculte = $request->request->get('faculte');

        if (empty($firstName)) {
            $this->addFlash('error', '❌ Le prénom est obligatoire');
            return false;
        }

        if (empty($lastName)) {
            $this->addFlash('error', '❌ Le nom est obligatoire');
            return false;
        }

        if (empty($email)) {
            $this->addFlash('error', '❌ L\'email est obligatoire');
            return false;
        }

        if (empty($faculte)) {
            $this->addFlash('error', '❌ La faculté est obligatoire');
            return false;
        }

        return true;
    }

    #[Route('/{id}/cancel', name: 'app_user_edit_cancel')]
    public function cancelEdit(): Response
    {
        return $this->redirectToRoute('app_user_index');
    }
}