<?php

namespace App\Controller\Role;

use App\Entity\Role\Role;
use App\Form\Role\RoleType;
use App\Service\Role\RoleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/roles')]
#[IsGranted('ROLE_ADMIN')]
class RoleController extends AbstractController
{
    public function __construct(
        private RoleService $roleService
    ) {}

    /**
     * Liste des rôles (équivalent de RoleController.java)
     */
    #[Route('/', name: 'app_role_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $search = $request->query->get('search', '');

        // Récupérer les rôles avec pagination
        $roles = $this->roleService->searchRoles($search, $page, 5);
        $total = $this->roleService->countSearchResults($search);
        $totalPages = ceil($total / 5);

        // Statistiques pour les KPI
        $stats = $this->roleService->getRoleStatistics();

        return $this->render('role/role.html.twig', [
            'roles' => $roles,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalResults' => $total,
            'search' => $search,
            'stats' => $stats
        ]);
    }

    /**
     * Création d'un nouveau rôle
     * Équivalent du bouton "Ajouter"
     */
    #[Route('/new', name: 'app_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $role = new Role();
        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->roleService->createRole($role);
                $this->addFlash('success', 'Rôle créé avec succès');
                return $this->redirectToRoute('app_role_index');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('role/new.html.twig', [
            'role' => $role,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Édition d'un rôle
     * Équivalent de EditRoleController.java
     */
    #[Route('/{id}/edit', name: 'app_role_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Role $role): Response
    {
        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->roleService->updateRole($role);
                $this->addFlash('success', 'Rôle modifié avec succès');
                return $this->redirectToRoute('app_role_index');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('role/edit.html.twig', [
            'role' => $role,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Suppression d'un rôle
     * Équivalent du bouton supprimer
     */
    #[Route('/{id}', name: 'app_role_delete', methods: ['POST'])]
    public function delete(Request $request, Role $role): Response
    {
        if ($this->isCsrfTokenValid('delete' . $role->getId(), $request->request->get('_token'))) {
            try {
                if ($this->roleService->deleteRole($role->getId())) {
                    $this->addFlash('success', 'Rôle supprimé avec succès');
                } else {
                    $this->addFlash('error', 'Impossible de supprimer le rôle');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_role_index');
    }

    /**
     * API pour rafraîchir les données
     * Équivalent de refreshData()
     */
    #[Route('/refresh', name: 'app_role_refresh', methods: ['POST'])]
    public function refresh(): Response
    {
        return $this->json([
            'total' => $this->roleService->getTotalRolesCount(),
            'stats' => $this->roleService->getRoleStatistics()
        ]);
    }
}