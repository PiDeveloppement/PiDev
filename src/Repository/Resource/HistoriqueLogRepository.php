<?php

namespace App\Repository\Resource;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User\UserModel;

class HistoriqueLogRepository
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function findByResource(string $resourceType, int $resourceId): array
    {
        $sql = "SELECT hl.*, COALESCE(CONCAT(um.First_Name, ' ', um.Last_Name), 'Utilisateur inconnu') as user_name, COALESCE(um.Email, '') as user_email 
                FROM historique_logs hl 
                LEFT JOIN user_model um ON hl.user_id = um.Id_User 
                WHERE resource_type = :resourceType AND resource_id = :resourceId 
                ORDER BY created_at DESC";
        
        return $this->em->getConnection()->fetchAllAssociative($sql, [
            'resourceType' => $resourceType,
            'resourceId' => $resourceId
        ]);
    }

    public function findRecentWithPagination(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT hl.*, COALESCE(CONCAT(um.First_Name, ' ', um.Last_Name), 'Utilisateur inconnu') as user_name, COALESCE(um.Email, '') as user_email 
                FROM historique_logs hl 
                LEFT JOIN user_model um ON hl.user_id = um.Id_User 
                WHERE 1=1";
        
        $params = [];
        
        // Appliquer les filtres
        if (!empty($filters['resourceType'])) {
            $sql .= " AND hl.resource_type = :resourceType";
            $params['resourceType'] = $filters['resourceType'];
        }

        if (!empty($filters['action'])) {
            $sql .= " AND hl.action = :action";
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['user'])) {
            $sql .= " AND (CONCAT(um.First_Name, ' ', um.Last_Name) LIKE :user OR um.Email LIKE :user)";
            $params['user'] = '%' . $filters['user'] . '%';
        }

        if (!empty($filters['startDate'])) {
            $sql .= " AND hl.created_at >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $sql .= " AND hl.created_at <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }

        $sql .= " ORDER BY hl.created_at DESC LIMIT $limit OFFSET $offset";
        
        return $this->em->getConnection()->fetchAllAssociative($sql, $params);
    }

    public function countByFilters(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as count 
                FROM historique_logs hl 
                LEFT JOIN user_model um ON hl.user_id = um.Id_User 
                WHERE 1=1";
        
        $params = [];
        
        // Appliquer les filtres (même logique que findRecentWithPagination)
        if (!empty($filters['resourceType'])) {
            $sql .= " AND hl.resource_type = :resourceType";
            $params['resourceType'] = $filters['resourceType'];
        }

        if (!empty($filters['action'])) {
            $sql .= " AND hl.action = :action";
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['user'])) {
            $sql .= " AND (CONCAT(um.First_Name, ' ', um.Last_Name) LIKE :user OR um.Email LIKE :user)";
            $params['user'] = '%' . $filters['user'] . '%';
        }

        if (!empty($filters['startDate'])) {
            $sql .= " AND hl.created_at >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $sql .= " AND hl.created_at <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }
        
        $result = $this->em->getConnection()->fetchOne($sql, $params);
        return (int) $result;
    }

    public function findByUser(UserModel $user, int $limit = 50): array
    {
        $sql = "SELECT hl.*, COALESCE(CONCAT(um.First_Name, ' ', um.Last_Name), 'Utilisateur inconnu') as user_name, COALESCE(um.Email, '') as user_email 
                FROM historique_logs hl 
                LEFT JOIN user_model um ON hl.user_id = um.Id_User 
                WHERE hl.user_id = :userId 
                ORDER BY hl.created_at DESC 
                LIMIT $limit";
        
        return $this->em->getConnection()->fetchAllAssociative($sql, [
            'userId' => $user->getId()
        ]);
    }

    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $sql = "SELECT hl.*, COALESCE(CONCAT(um.First_Name, ' ', um.Last_Name), 'Utilisateur inconnu') as user_name, COALESCE(um.Email, '') as user_email 
                FROM historique_logs hl 
                LEFT JOIN user_model um ON hl.user_id = um.Id_User 
                WHERE hl.created_at BETWEEN :startDate AND :endDate 
                ORDER BY hl.created_at DESC";
        
        return $this->em->getConnection()->fetchAllAssociative($sql, [
            'startDate' => $startDate->format('Y-m-d H:i:s'),
            'endDate' => $endDate->format('Y-m-d H:i:s')
        ]);
    }
}
