<?php

namespace App\Controller\Resource;

use App\Repository\Resource\HistoriqueLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/historique')]
class AuditController extends AbstractController
{
    #[Route('/logs', name: 'app_historique_logs', methods: ['GET'])]
    public function index(Request $request, HistoriqueLogRepository $repository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, $request->query->getInt('limit', 20));
        
        // Récupérer les filtres
        $resourceType = $request->query->get('resourceType');
        $action = $request->query->get('action');
        $user = $request->query->get('user');
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');
        
        // Construire les filtres pour le repository
        $filters = [];
        if ($resourceType && is_string($resourceType)) {
            $filters['resourceType'] = $resourceType;
        }
        if ($action && is_string($action)) {
            $filters['action'] = $action;
        }
        if ($user && is_string($user)) {
            $filters['user'] = $user;
        }
        if ($startDate && is_string($startDate)) {
            $filters['startDate'] = $startDate;
        }
        if ($endDate && is_string($endDate)) {
            $filters['endDate'] = $endDate;
        }
        
        // Récupérer les logs avec pagination
        $logs = $repository->findRecentWithPagination($page, $limit, $filters);
        $totalLogs = $repository->countByFilters($filters);
        
        // Calculer les informations de pagination
        $totalPages = ceil($totalLogs / $limit);
        $hasNextPage = $page < $totalPages;
        $hasPreviousPage = $page > 1;
        
        return $this->render('audit/index.html.twig', [
            'logs' => $logs,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
            'limit' => $limit,
            'hasNextPage' => $hasNextPage,
            'hasPreviousPage' => $hasPreviousPage,
            'filters' => $filters,
            'resourceTypes' => ['RESERVATION', 'SALLE', 'EQUIPEMENT'],
            'actions' => ['CREATE', 'UPDATE', 'DELETE']
        ]);
    }
    
    #[Route('/logs/recent', name: 'app_historique_logs_recent', methods: ['GET'])]
    public function recentLogs(Request $request, HistoriqueLogRepository $repository): JsonResponse
    {
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));
        
        $logs = $repository->findRecentWithPagination(1, $limit);
        
        $logsData = [];
        foreach ($logs as $log) {
            $logsData[] = [
                'id' => $log['id'],
                'action' => $log['action'],
                'actionLabel' => $this->getActionLabel($log['action']),
                'resourceType' => $log['resource_type'],
                'resourceTypeLabel' => $this->getResourceTypeLabel($log['resource_type']),
                'resourceId' => $log['resource_id'],
                'resourceName' => $log['resource_name'],
                'oldValues' => $log['old_values'],
                'newValues' => $log['new_values'],
                'changes' => $this->getChanges($log['old_values'], $log['new_values']),
                'createdAt' => $log['created_at'],
                'ipAddress' => $log['ip_address'],
                'userAgent' => $log['user_agent'],
                'userName' => $log['user_name'],
                'userEmail' => $log['user_email'],
                'description' => $this->getDescription([
                    'user_name' => $log['user_name'],
                    'created_at' => $log['created_at'],
                    'resource_name' => $log['resource_name'],
                    'action' => $log['action']
                ])
            ];
        }
        
        return new JsonResponse([
            'logs' => $logsData,
            'total' => count($logsData)
        ]);
    }
    
    #[Route('/logs/resource/{resourceType}/{resourceId}', name: 'app_historique_logs_resource', methods: ['GET'])]
    public function resourceLogs(
        string $resourceType, 
        int $resourceId, 
        HistoriqueLogRepository $repository
    ): JsonResponse {
        $logs = $repository->findByResource($resourceType, $resourceId);
        
        $logsData = [];
        foreach ($logs as $log) {
            $logsData[] = [
                'id' => $log['id'],
                'action' => $log['action'],
                'actionLabel' => $this->getActionLabel($log['action']),
                'resourceType' => $log['resource_type'],
                'resourceTypeLabel' => $this->getResourceTypeLabel($log['resource_type']),
                'resourceId' => $log['resource_id'],
                'resourceName' => $log['resource_name'],
                'oldValues' => $log['old_values'],
                'newValues' => $log['new_values'],
                'changes' => $this->getChanges($log['old_values'], $log['new_values']),
                'createdAt' => $log['created_at'],
                'ipAddress' => $log['ip_address'],
                'userAgent' => $log['user_agent'],
                'userName' => $log['user_name'],
                'userEmail' => $log['user_email'],
                'description' => $this->getDescription([
                    'user_name' => $log['user_name'],
                    'created_at' => $log['created_at'],
                    'resource_name' => $log['resource_name'],
                    'action' => $log['action']
                ])
            ];
        }

        return new JsonResponse([
            'logs' => $logsData,
            'total' => count($logsData)
        ]);
    }

    private function getActionLabel(string $action): string
    {
        return match($action) {
            'CREATE' => 'Création',
            'UPDATE' => 'Modification',
            'DELETE' => 'Suppression',
            default => $action
        };
    }

    private function getResourceTypeLabel(string $resourceType): string
    {
        return match($resourceType) {
            'RESERVATION' => 'Réservation',
            'SALLE' => 'Salle',
            'EQUIPEMENT' => 'Équipement',
            default => $resourceType
        };
    }

    /**
     * Extract changes from old and new values
     * @return array<string, array{old: mixed, new: mixed}>|null
     */
    private function getChanges(?string $oldValues, ?string $newValues): ?array
    {
        if ($oldValues || $newValues) {
            $old = $oldValues ? json_decode($oldValues, true) : [];
            $new = $newValues ? json_decode($newValues, true) : [];
            
            /** @var array<string, array{old: mixed, new: mixed}> $changes */
            $changes = [];
            foreach ($old as $key => $oldValue) {
                if (isset($new[$key]) && $oldValue !== $new[$key]) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $new[$key]
                    ];
                }
            }
            
            return $changes;
        }
        
        return null;
    }

    /**
     * Generate description from log data
     * @param array{user_name?: string, created_at: string|\DateTime, resource_name?: string, action?: string} $log
     */
    private function getDescription(array $log): string
    {
        /** @var array{user_name?: string, created_at: string|\DateTime, resource_name?: string, action?: string} $log */
        $userName = $log['user_name'] ?? 'Utilisateur inconnu';
        $resourceName = $log['resource_name'] ?? 'Ressource inconnue';
        $action = $log['action'] ?? 'UNKNOWN';
        
        // created_at peut être un objet DateTime ou une chaîne
        if ($log['created_at'] instanceof \DateTime) {
            $createdAt = $log['created_at'];
        } else {
            $createdAt = new \DateTime($log['created_at']);
        }
        $time = $createdAt->format('H:i');
        
        return sprintf(
            "%s %s par %s à %s",
            $resourceName,
            $this->getActionLabel($action),
            $userName,
            $time
        );
    }
}