<?php

namespace App\Controller\Role;

use App\Entity\Role\Role;
use App\Repository\Role\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/roles')]
#[IsGranted('ROLE_ADMIN')]
class RoleController extends AbstractController
{
    private const ITEMS_PER_PAGE = 5; // 5 lignes par page

    public function __construct(
        private RoleRepository $roleRepository,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager
    ) {}

    // ==================== INDEX (Liste des rôles) ====================
    
    #[Route('/', name: 'app_role_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim($request->query->get('search', ''));
        $limit = self::ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;

        if ($search !== '') {
            $roles = $this->roleRepository->searchByName($search, $limit, $offset);
            $totalResults = $this->roleRepository->countSearchResults($search);
        } else {
            $roles = $this->roleRepository->findBy([], ['id' => 'DESC'], $limit, $offset);
            $totalResults = $this->roleRepository->count([]);
        }

        $totalPages = ceil($totalResults / $limit);

        return $this->render('role/role.html.twig', [
            'roles' => $roles,
            'search' => $search,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalResults' => $totalResults,
            'itemsPerPage' => self::ITEMS_PER_PAGE,
        ]);
    }

    #[Route('/search', name: 'app_role_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        
        $roles = $this->roleRepository->searchByName($query, $limit, $offset);
        $totalResults = $this->roleRepository->countSearchResults($query);
        $totalPages = ceil($totalResults / $limit);
        
        $data = [];
        foreach ($roles as $role) {
            $data[] = [
                'id' => $role->getId(),
                'roleName' => $role->getRoleName(),
                'csrfToken' => $this->csrfTokenManager->getToken('delete' . $role->getId())->getValue(),
            ];
        }
        
        return $this->json([
            'roles' => $data,
            'totalResults' => $totalResults,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'itemsPerPage' => $limit
        ]);
    }

    // ==================== NEW & CREATE ====================
    
    #[Route('/new', name: 'app_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $roleName = trim($request->request->get('role_name'));
            
            if (empty($roleName)) {
                $this->addFlash('error', '❌ Le nom du rôle est obligatoire');
                return $this->redirectToRoute('app_role_new');
            }
            
            if (strlen($roleName) < 2) {
                $this->addFlash('error', '❌ Le nom du rôle doit contenir au moins 2 caractères');
                return $this->redirectToRoute('app_role_new');
            }
            
            $existingRole = $this->roleRepository->findOneByName($roleName);
            if ($existingRole) {
                $this->addFlash('error', '❌ Un rôle avec le nom "' . $roleName . '" existe déjà');
                return $this->redirectToRoute('app_role_new');
            }
            
            try {
                $role = new Role($roleName);
                $this->entityManager->persist($role);
                $this->entityManager->flush();
                
                $this->addFlash('success', '✅ Rôle "' . $roleName . '" ajouté avec succès');
                return $this->redirectToRoute('app_role_index');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Erreur lors de l\'ajout: ' . $e->getMessage());
                return $this->redirectToRoute('app_role_new');
            }
        }
        
        return $this->render('role/edit.html.twig', [
            'mode' => 'add',
            'role' => null,
        ]);
    }

    // ==================== EDIT & UPDATE ====================
    
    #[Route('/{id}/edit', name: 'app_role_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $role = $this->roleRepository->find($id);
        
        if (!$role) {
            $this->addFlash('error', '❌ Rôle non trouvé');
            return $this->redirectToRoute('app_role_index');
        }
        
        if ($request->isMethod('POST')) {
            $newRoleName = trim($request->request->get('role_name'));
            
            if (empty($newRoleName)) {
                $this->addFlash('error', '❌ Le nom du rôle est obligatoire');
                return $this->redirectToRoute('app_role_edit', ['id' => $id]);
            }
            
            if (strlen($newRoleName) < 2) {
                $this->addFlash('error', '❌ Le nom du rôle doit contenir au moins 2 caractères');
                return $this->redirectToRoute('app_role_edit', ['id' => $id]);
            }
            
            $existingRole = $this->roleRepository->findOneByName($newRoleName);
            if ($existingRole && $existingRole->getId() !== $role->getId()) {
                $this->addFlash('error', '❌ Un rôle avec le nom "' . $newRoleName . '" existe déjà');
                return $this->redirectToRoute('app_role_edit', ['id' => $id]);
            }
            
            try {
                $role->setRoleName($newRoleName);
                $this->entityManager->flush();
                
                $this->addFlash('success', '✅ Rôle modifié avec succès');
                return $this->redirectToRoute('app_role_index');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Erreur lors de la modification: ' . $e->getMessage());
                return $this->redirectToRoute('app_role_edit', ['id' => $id]);
            }
        }
        
        return $this->render('role/edit.html.twig', [
            'mode' => 'edit',
            'role' => $role,
        ]);
    }

    // ==================== DELETE ====================
    
    #[Route('/{id}', name: 'app_role_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $role = $this->roleRepository->find($id);
        
        if (!$role) {
            $this->addFlash('error', '❌ Rôle non trouvé');
            return $this->redirectToRoute('app_role_index');
        }
        
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $id, $token)) {
            $this->addFlash('error', '❌ Token CSRF invalide');
            return $this->redirectToRoute('app_role_index');
        }
        
        try {
            $roleName = $role->getRoleName();
            $this->entityManager->remove($role);
            $this->entityManager->flush();
            
            $this->addFlash('success', '✅ Rôle "' . $roleName . '" supprimé avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Impossible de supprimer ce rôle: ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_role_index');
    }
}