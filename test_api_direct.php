<?php

require_once 'vendor/autoload.php';

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use App\Kernel;

try {
    $kernel = new App\Kernel('dev', true);
    $kernel->boot();

    $container = $kernel->getContainer();

    // Test direct du repository
    $repository = $container->get(App\Repository\HistoriqueLogRepository::class);
    $logs = $repository->findRecentWithPagination(1, 5);

    echo "=== Test direct du repository ===\n";
    echo "Nombre de logs trouvés: " . count($logs) . "\n\n";

    if (!empty($logs)) {
        echo "Premier log trouvé:\n";
        print_r($logs[0]);
        echo "\n";

        // Simuler la réponse JSON du contrôleur
        $logsData = [];
        foreach ($logs as $log) {
            $logsData[] = [
                'id' => $log['id'],
                'action' => $log['action'],
                'actionLabel' => $log['action'] === 'CREATE' ? 'Création' : ($log['action'] === 'UPDATE' ? 'Modification' : 'Suppression'),
                'resourceType' => $log['resource_type'],
                'resourceTypeLabel' => $log['resource_type'] === 'RESERVATION' ? 'Réservation' : ($log['resource_type'] === 'SALLE' ? 'Salle' : 'Équipement'),
                'resourceId' => $log['resource_id'],
                'resourceName' => $log['resource_name'],
                'oldValues' => $log['old_values'],
                'newValues' => $log['new_values'],
                'changes' => null, // Simplifié pour le test
                'createdAt' => $log['created_at'],
                'ipAddress' => $log['ip_address'],
                'userAgent' => $log['user_agent'],
                'userName' => $log['user_name'] ?? null,
                'userEmail' => $log['user_email'] ?? null,
                'description' => $log['resource_name'] . ' ' . ($log['action'] === 'CREATE' ? 'Création' : ($log['action'] === 'UPDATE' ? 'Modification' : 'Suppression')) . ' par ' . ($log['user_name'] ?? 'Utilisateur inconnu')
            ];
        }

        echo "=== Simulation de la réponse JSON ===\n";
        echo json_encode([
            'logs' => $logsData,
            'total' => count($logsData)
        ], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Aucun log trouvé dans la base de données.\n";
    }

} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
