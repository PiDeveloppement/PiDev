<?php

namespace App\Controller\Role;

use App\Entity\Role\Role;
use App\Repository\Role\RoleRepository;
use App\Service\Role\RoleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/roles')]
#[IsGranted('ROLE_ADMIN')]
class EditRoleController extends AbstractController
{
    public function __construct(
        private RoleService $roleService,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/new', name: 'app_role_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleAdd($request);
        }

        return $this->render('role/edit.html.twig', [
            'mode' => 'add',
            'formTitle' => '➕ Nouveau rôle',
            'formHint' => 'Ajoutez un nouveau rôle',
            'buttonText' => '➕ Ajouter',
            'buttonStyle' => 'bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700'
        ]);
    }

    #[Route('/{id}/edit', name: 'app_role_edit')]
    public function edit(Request $request, int $id): Response
    {
        $role = $this->roleRepository->find($id);
        
        if (!$role) {
            $this->addFlash('error', 'Rôle non trouvé');
            return $this->redirectToRoute('app_role_index');
        }

        if ($request->isMethod('POST')) {
            return $this->handleEdit($request, $role);
        }

        return $this->render('role/edit.html.twig', [
            'mode' => 'edit',
            'role' => $role,
            'formTitle' => '✏️ Modification du rôle',
            'formHint' => 'Modifiez le rôle: ' . $role->getRoleName(),
            'buttonText' => '💾 Enregistrer',
            'buttonStyle' => 'bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'
        ]);
    }

    private function handleAdd(Request $request): Response
    {
        if (!$this->validateFields($request)) {
            return $this->redirectToRoute('app_role_new');
        }

        $roleName = $request->request->get('role_name');

        try {
            // Vérifier si le rôle existe déjà
            if ($this->roleRepository->findOneByName($roleName)) {
                $this->addFlash('error', '❌ Un rôle avec ce nom existe déjà');
                return $this->redirectToRoute('app_role_new');
            }

            $role = new Role($roleName);
            
            if ($this->roleService->createRole($role)) {
                $this->addFlash('success', '✅ Rôle ajouté avec succès');
                return $this->redirectToRoute('app_role_index');
            }

            $this->addFlash('error', '❌ Échec de l\'ajout du rôle');
            
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_role_new');
    }

    private function handleEdit(Request $request, Role $role): Response
    {
        if (!$this->validateFields($request)) {
            return $this->redirectToRoute('app_role_edit', ['id' => $role->getId()]);
        }

        $newRoleName = $request->request->get('role_name');

        try {
            // Vérifier si le nouveau nom existe déjà (pour un autre rôle)
            $existingRole = $this->roleRepository->findOneByName($newRoleName);
            if ($existingRole && $existingRole->getId() !== $role->getId()) {
                $this->addFlash('error', '❌ Un rôle avec ce nom existe déjà');
                return $this->redirectToRoute('app_role_edit', ['id' => $role->getId()]);
            }

            $role->setRoleName($newRoleName);
            $this->entityManager->flush();

            $this->addFlash('success', '✅ Rôle modifié avec succès');
            
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_role_index');
    }

    private function validateFields(Request $request): bool
    {
        $roleName = $request->request->get('role_name');

        if (empty($roleName) || trim($roleName) === '') {
            $this->addFlash('error', '❌ Le nom du rôle est obligatoire');
            return false;
        }

        return true;
    }

    #[Route('/{id}/cancel', name: 'app_role_edit_cancel')]
    public function cancelEdit(): Response
    {
        return $this->redirectToRoute('app_role_index');
    }
}