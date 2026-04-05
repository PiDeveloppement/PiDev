<?php

namespace App\Controller\User;

use App\Entity\User\UserModel;
use App\Form\User\UserType;
use App\Service\User\UserService;
use App\Repository\User\UserRepository;
use App\Repository\Role\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager
    ) {}

    // ==================== INDEX (Liste des utilisateurs) ====================
    
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $keyword = $request->query->get('search');
        $faculte = $request->query->get('faculte');
        $role = $request->query->get('role');

        $users = $this->userService->searchUsers($keyword, $faculte, $role, $page, 5);
        $total = $this->userService->countUsers($keyword, $faculte, $role);
        
        $facultes = $this->userService->getAllFacultes();
        $roles = $this->roleRepository->findAllNames();

        // Récupération correcte des statistiques par rôle
        $allRoles = $this->roleRepository->findAll();
        $statsByRole = [];
        
        foreach ($allRoles as $roleEntity) {
            $roleName = $roleEntity->getRoleName();
            $count = $this->userRepository->count(['role' => $roleEntity]);
            $statsByRole[$roleName] = $count;
        }
        
        // Ajouter les utilisateurs sans rôle
        $defaultCount = $this->userRepository->count(['role' => null]);
        $statsByRole['Default'] = $defaultCount;

        $stats = [
            'total' => $this->userRepository->count([]),
            'new_this_month' => $this->getNewUsersThisMonthCount(),
            'by_role' => $statsByRole
        ];

        return $this->render('user/user.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => ceil($total / 5),
            'totalResults' => $total,
            'facultes' => $facultes,
            'roles' => $roles,
            'stats' => $stats,
            'searchKeyword' => $keyword,
            'selectedFaculte' => $faculte,
            'selectedRole' => $role
        ]);
    }

    /**
     * Récupère le nombre de nouveaux utilisateurs ce mois
     * Utilise registrationDate au lieu de createdAt
     */
    private function getNewUsersThisMonthCount(): int
    {
        $now = new \DateTime();
        $startOfMonth = new \DateTime($now->format('Y-m-01 00:00:00'));
        
        // Utilisez le QueryBuilder car registrationDate peut être nullable
        $qb = $this->userRepository->createQueryBuilder('u');
        $qb->select('COUNT(u.id)')
           ->where('u.registrationDate >= :startOfMonth')
           ->setParameter('startOfMonth', $startOfMonth);
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    // ==================== NEW & CREATE ====================
    
    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleCreate($request, $passwordHasher);
        }

        $roles = $this->roleRepository->findAllNames();

        return $this->render('user/new.html.twig', [
            'roles' => $roles,
        ]);
    }

    private function handleCreate(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        // Validation des champs
        if (!$this->validateFields($request)) {
            return $this->redirectToRoute('app_user_new');
        }

        $firstName = trim($request->request->get('first_name'));
        $lastName = trim($request->request->get('last_name'));
        $email = trim($request->request->get('email'));
        $faculte = trim($request->request->get('faculte'));
        $password = $request->request->get('password');
        $roleName = $request->request->get('role');

        // Validation du mot de passe
        if (empty($password) || strlen($password) < 6) {
            $this->addFlash('error', '❌ Le mot de passe doit contenir au moins 6 caractères');
            return $this->redirectToRoute('app_user_new');
        }

        try {
            $user = new UserModel();
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setEmail($email);
            $user->setFaculte($faculte);
            $user->setRegistrationDate(new \DateTime()); // Définit la date d'inscription
            
            // Hasher le mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Gestion du rôle
            if ($roleName) {
                $role = $this->roleRepository->findOneByName($roleName);
                if ($role) {
                    $user->setRole($role);
                    $user->setRoleId($role->getId());
                }
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', '✅ Utilisateur créé avec succès');
            return $this->redirectToRoute('app_user_index');

        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur lors de la création: ' . $e->getMessage());
            return $this->redirectToRoute('app_user_new');
        }
    }

    // ==================== EDIT & UPDATE ====================
    
    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->userRepository->findByIdWithRole($id);
        
        if (!$user) {
            $this->addFlash('error', '❌ Utilisateur non trouvé');
            return $this->redirectToRoute('app_user_index');
        }

        if ($request->isMethod('POST')) {
            return $this->handleEdit($request, $user);
        }

        $roles = $this->roleRepository->findAllNames();

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

        $firstName = trim($request->request->get('first_name'));
        $lastName = trim($request->request->get('last_name'));
        $email = trim($request->request->get('email'));
        $faculte = trim($request->request->get('faculte'));
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
                $this->addFlash('error', '❌ Rôle invalide');
                return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
            }
        }

        try {
            $this->entityManager->flush();
            $this->addFlash('success', '✅ Utilisateur mis à jour avec succès');
            return $this->redirectToRoute('app_user_index');
            
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Échec de la mise à jour: ' . $e->getMessage());
            return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
        }
    }

    // ==================== DELETE ====================
    
    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            $this->addFlash('error', '❌ Utilisateur non trouvé');
            return $this->redirectToRoute('app_user_index');
        }
        
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $id, $token)) {
            $this->addFlash('error', '❌ Token CSRF invalide');
            return $this->redirectToRoute('app_user_index');
        }
        
        try {
            $userName = $user->getFullName();
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            
            $this->addFlash('success', '✅ Utilisateur "' . $userName . '" supprimé avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Impossible de supprimer l\'utilisateur: ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_user_index');
    }

    // ==================== SEARCH & AJAX ====================
    
    #[Route('/search', name: 'app_user_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        $faculte = trim($request->query->get('faculte', ''));
        $role = trim($request->query->get('role', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 5;
        
        $users = $this->userService->searchUsers($query, $faculte, $role, $page, $limit);
        $totalResults = $this->userService->countUsers($query, $faculte, $role);
        $totalPages = ceil($totalResults / $limit);
        
        // Récupération correcte des statistiques par rôle
        $allRoles = $this->roleRepository->findAll();
        $statsByRole = [];
        
        foreach ($allRoles as $roleEntity) {
            $roleName = $roleEntity->getRoleName();
            $count = $this->userRepository->count(['role' => $roleEntity]);
            $statsByRole[$roleName] = $count;
        }
        
        $defaultCount = $this->userRepository->count(['role' => null]);
        $statsByRole['Default'] = $defaultCount;
        
        $stats = [
            'total' => $this->userRepository->count([]),
            'new_this_month' => $this->getNewUsersThisMonthCount(),
            'by_role' => $statsByRole
        ];
        
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'faculte' => $user->getFaculte(),
                'role' => $user->getRole()?->getRoleName() ?? 'Default',
                'csrfToken' => $this->csrfTokenManager->getToken('delete' . $user->getId())->getValue(),
            ];
        }
        
        return $this->json([
            'users' => $data,
            'total' => $totalResults,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'stats' => $stats
        ]);
    }

    // ==================== UTILS ====================
    
    private function validateFields(Request $request): bool
    {
        $firstName = trim($request->request->get('first_name'));
        $lastName = trim($request->request->get('last_name'));
        $email = trim($request->request->get('email'));
        $faculte = trim($request->request->get('faculte'));

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

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', '❌ L\'email n\'est pas valide');
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

    #[Route('/refresh', name: 'app_user_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        $stats = [
            'total' => $this->userRepository->count([]),
            'new_this_month' => $this->getNewUsersThisMonthCount(),
            'by_role' => $this->getStatsByRole()
        ];
        
        return $this->json($stats);
    }

    private function getStatsByRole(): array
    {
        $allRoles = $this->roleRepository->findAll();
        $statsByRole = [];
        
        foreach ($allRoles as $roleEntity) {
            $roleName = $roleEntity->getRoleName();
            $count = $this->userRepository->count(['role' => $roleEntity]);
            $statsByRole[$roleName] = $count;
        }
        
        $defaultCount = $this->userRepository->count(['role' => null]);
        $statsByRole['Default'] = $defaultCount;
        
        return $statsByRole;
    }

    #[Route('/export', name: 'app_user_export')]
    public function export(): JsonResponse
    {
        $users = $this->userService->getAllUsers();
        
        return $this->json([
            'message' => 'Export en cours de développement',
            'count' => count($users)
        ]);
    }
}