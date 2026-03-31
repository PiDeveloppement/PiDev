<?php

namespace App\Service\Role;

use App\Entity\Role\Role;
use App\Repository\Role\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RoleService
{
    public function __construct(
        private RoleRepository $roleRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    // ==================== CRUD PRINCIPAL ====================

    /**
     * Récupère tous les rôles
     * Équivalent de getAllRoles()
     */
    public function getAllRoles(): array
    {
        return $this->roleRepository->findAllOrdered();
    }

    /**
     * Récupère un rôle par ID
     */
    public function getRoleById(int $id): ?Role
    {
        return $this->roleRepository->findById($id);
    }

    /**
     * Récupère un rôle par nom
     * Équivalent de getRoleIdByName() mais retourne l'entité
     */
    public function getRoleByName(string $roleName): ?Role
    {
        return $this->roleRepository->findOneByName($roleName);
    }

    /**
     * Ajoute un nouveau rôle
     * Équivalent de addRole()
     */
    public function createRole(Role $role): Role
    {
        // Vérifier si le rôle existe déjà
        $existing = $this->roleRepository->findOneByName($role->getRoleName());
        if ($existing) {
            throw new \Exception('Un rôle avec ce nom existe déjà');
        }

        $this->entityManager->persist($role);
        $this->entityManager->flush();

        $this->logger->info('Rôle créé: ' . $role->getRoleName());
        return $role;
    }

    /**
     * Met à jour un rôle
     * Équivalent de updateRole()
     */
    public function updateRole(Role $role): Role
    {
        // Vérifier si un autre rôle a déjà ce nom
        $existing = $this->roleRepository->findOneByName($role->getRoleName());
        if ($existing && $existing->getId() !== $role->getId()) {
            throw new \Exception('Un rôle avec ce nom existe déjà');
        }

        $this->entityManager->flush();
        $this->logger->info('Rôle mis à jour: ' . $role->getRoleName());

        return $role;
    }

    /**
     * Supprime un rôle
     * Équivalent de deleteRole()
     */
    public function deleteRole(int $id): bool
    {
        $role = $this->roleRepository->find($id);
        if (!$role) {
            return false;
        }

        // Vérifier si le rôle est utilisé par des utilisateurs
        if (count($role->getUsers()) > 0) {
            throw new \Exception('Ce rôle est utilisé par des utilisateurs et ne peut pas être supprimé');
        }

        try {
            $this->entityManager->remove($role);
            $this->entityManager->flush();
            $this->logger->info('Rôle supprimé: ' . $role->getRoleName());
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur suppression rôle: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== MÉTHODES DE RECHERCHE ====================

    /**
     * Récupère tous les noms de rôles
     * Équivalent de getAllRoleNames()
     */
    public function getAllRoleNames(): array
    {
        return $this->roleRepository->findAllNames();
    }

    /**
     * Récupère l'ID d'un rôle par son nom
     * Équivalent de getRoleIdByName()
     */
    public function getRoleIdByName(string $roleName): ?int
    {
        return $this->roleRepository->findIdByName($roleName);
    }

    /**
     * Recherche des rôles par mot-clé
     * Équivalent du filtre de recherche dans le contrôleur
     */
    public function searchRoles(string $keyword, int $page = 1, int $limit = 5): array
    {
        if (empty($keyword)) {
            return $this->getRolesPage($page, $limit);
        }
        return $this->roleRepository->searchByName($keyword, $page, $limit);
    }

    /**
     * Compte les résultats de recherche
     */
    public function countSearchResults(?string $keyword = null): int
    {
        if (empty($keyword)) {
            return $this->getTotalRolesCount();
        }
        return $this->roleRepository->countSearch($keyword);
    }

    // ==================== STATISTIQUES ====================

    /**
     * Compte le nombre total de rôles
     * Équivalent de getTotalRolesCount()
     */
    public function getTotalRolesCount(): int
    {
        return $this->roleRepository->countAll();
    }

    /**
     * Récupère les statistiques d'utilisation des rôles
     */
    public function getRoleStatistics(): array
    {
        return [
            'total' => $this->getTotalRolesCount(),
            'usage' => $this->roleRepository->getUsageStatistics()
        ];
    }

    // ==================== PAGINATION ====================

    /**
     * Récupère une page de rôles
     * Équivalent de la logique de pagination dans RoleController
     */
    public function getRolesPage(int $page = 1, int $limit = 5): array
    {
        return $this->roleRepository->findPage($page, $limit);
    }

    /**
     * Vérifie si un nom de rôle existe déjà
     */
    public function isRoleNameExists(string $roleName, ?int $excludeId = null): bool
    {
        $role = $this->roleRepository->findOneByName($roleName);
        if (!$role) {
            return false;
        }
        if ($excludeId && $role->getId() === $excludeId) {
            return false;
        }
        return true;
    }
}