<?php

namespace App\Service\Role;

/**
 * Service pour l'assignation automatique des rôles basée sur le domaine de l'email
 */
class RoleAssignmentService
{
    // Mapping des domaines vers les IDs de rôles
    private array $domainRoleMapping = [
        // Sponsors tunisiens (roleId = 4)
        'oreedoo.tn' => 4,
        'oreedoo.com' => 4,
        'orange.tn' => 4,
        'orange.com' => 4,
        'tunisietelecom.tn' => 4,
        'tunisietelecom.com' => 4,
        'ooredoo.tn' => 4,
        'ooredoo.com' => 4,
        'topnet.tn' => 4,
        'globalnet.tn' => 4,
        'hexabyte.tn' => 4,
        
        // Admins (roleId = 2)
        'esprit.tn' => 2,
        'esprittn.com' => 2,
        'enig.tn' => 2,
        'enig.rnu.tn' => 2,
        'supcom.tn' => 2,
        'ensi.rnu.tn' => 2,
        'insat.rnu.tn' => 2,
        'isimm.rnu.tn' => 2,
        
        // Organisateurs (roleId = 3)
        'eventflow.tn' => 3,
        'eventflow.com' => 3,
        'tunisie-events.tn' => 3,
        'tunisia-events.com' => 3,
    ];

    /**
     * Détermine le rôle basé sur le domaine de l'email
     *
     * @param string $email L'email de l'utilisateur
     * @return int L'ID du rôle assigné
     */
    public function assignRoleByEmail(string $email): int
    {
        // Extraire le domaine de l'email
        $domain = $this->extractDomain($email);
        
        if (!$domain) {
            // Si pas de domaine valide, assigner rôle par défaut (participant = 1)
            return 1;
        }
        
        // Chercher le rôle correspondant au domaine
        $domainLower = strtolower($domain);
        
        foreach ($this->domainRoleMapping as $mappedDomain => $roleId) {
            if (strtolower($mappedDomain) === $domainLower) {
                return $roleId;
            }
        }
        
        // Par défaut, assigner rôle participant (roleId = 1)
        return 1;
    }

    /**
     * Extrait le domaine d'une adresse email
     *
     * @param string $email L'email
     * @return string|null Le domaine ou null si invalide
     */
    private function extractDomain(string $email): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        $parts = explode('@', $email);
        return $parts[1] ?? null;
    }

    /**
     * Ajoute un nouveau mapping domaine/rôle
     *
     * @param string $domain Le domaine email
     * @param int $roleId L'ID du rôle
     */
    public function addDomainMapping(string $domain, int $roleId): void
    {
        $this->domainRoleMapping[$domain] = $roleId;
    }

    /**
     * Retourne tous les mappings
     *
     * @return array
     */
    public function getDomainMappings(): array
    {
        return $this->domainRoleMapping;
    }

    /**
     * Vérifie si un domaine est mappé à un rôle spécifique
     *
     * @param string $domain Le domaine
     * @param int $roleId L'ID du rôle
     * @return bool
     */
    public function isDomainMappedToRole(string $domain, int $roleId): bool
    {
        return isset($this->domainRoleMapping[$domain]) && $this->domainRoleMapping[$domain] === $roleId;
    }
}
