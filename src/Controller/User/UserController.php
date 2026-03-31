<?php

namespace App\Controller\User;

use App\Entity\User\UserModel;  // ← Correction: majuscule à User (PascalCase)
use App\Form\User\UserType;
use App\Service\User\UserService;  // ← Correction: majuscule à User
use App\Repository\User\UserRepository;  // ← Correction: majuscule à User
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Liste des utilisateurs (équivalent de UserController.java)
     */
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Récupérer les paramètres de filtrage (équivalent des ComboBox)
        $page = $request->query->getInt('page', 1);
        $keyword = $request->query->get('search');
        $faculte = $request->query->get('faculte');
        $role = $request->query->get('role');

        // Appliquer les filtres et la pagination
        $users = $this->userService->searchUsers($keyword, $faculte, $role, $page, 5);
        $total = $this->userService->countUsers($keyword, $faculte, $role);
        
        // Récupérer les listes pour les filtres
        $facultes = $this->userService->getAllFacultes();
        
        // ✅ Correction: Utiliser RoleRepository pour récupérer les rôles
        $roles = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT r.roleName')
            ->from('App\Entity\Role', 'r')
            ->orderBy('r.roleName', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        // Statistiques pour les KPI
        $stats = [
            'total' => $this->userService->getTotalUsersCount(),
            'new_this_month' => $this->userService->getNewUsersThisMonthCount(),
            'by_role' => $this->userService->getUsersCountByRole()
        ];

        return $this->render('user/index.html.twig', [  // ✅ Correction: user/index.html.twig
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
     * Création d'un nouvel utilisateur
     * Équivalent de l'ouverture du formulaire dans JavaFX
     */
    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new UserModel();
        $form = $this->createForm(UserType::class, $user, [
            'is_creation' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            
            // ✅ Hasher le mot de passe avant de sauvegarder
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            
            $this->userService->createUser($user);

            $this->addFlash('success', 'Utilisateur créé avec succès');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Édition d'un utilisateur
     * Équivalent de EditUserController.java
     */
    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserModel $user, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('plainPassword')->getData();
            
            // ✅ Hasher le mot de passe si fourni
            if ($newPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
            }
            
            $this->entityManager->flush();

            $this->addFlash('success', 'Utilisateur modifié avec succès');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Suppression d'un utilisateur
     * Équivalent de confirmAndDelete() dans UserController.java
     */
    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, UserModel $user): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            try {
                $this->entityManager->remove($user);
                $this->entityManager->flush();
                $this->addFlash('success', 'Utilisateur supprimé avec succès');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Impossible de supprimer l\'utilisateur: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_user_index');
    }

    /**
     * API pour rafraîchir les données (équivalent de refreshData())
     */
    #[Route('/refresh', name: 'app_user_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        return $this->json([
            'total' => $this->userService->getTotalUsersCount(),
            'new_this_month' => $this->userService->getNewUsersThisMonthCount()
        ]);
    }

    /**
     * Recherche d'utilisateurs (AJAX)
     */
    #[Route('/search', name: 'app_user_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $faculte = $request->query->get('faculte', '');
        $role = $request->query->get('role', '');

        $users = $this->userService->searchUsers($query, $faculte, $role, 1, 100);
        
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'faculte' => $user->getFaculte(),
                'role' => $user->getRole()?->getRoleName() ?? 'Utilisateur',
                'csrfToken' => $this->container->get('security.csrf.token_manager')
                    ->getToken('delete' . $user->getId())->getValue()
            ];
        }

        return $this->json([
            'users' => $data,
            'total' => count($data)
        ]);
    }

    /**
     * Export des utilisateurs (similaire à l'export PDF dans EventListController)
     */
    #[Route('/export', name: 'app_user_export')]
    public function export(): JsonResponse
    {
        $users = $this->userService->getAllUsers();
        
        // Logique d'export (CSV, PDF, etc.)
        // À implémenter selon vos besoins
        
        return $this->json([
            'message' => 'Export en cours de développement',
            'count' => count($users)
        ]);
    }
}