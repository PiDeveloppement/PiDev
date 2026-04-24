<?php

require_once 'vendor/autoload.php';

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use App\Kernel;

$kernel = new App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();

// Test du repository
$repository = $container->get(App\Repository\HistoriqueLogRepository::class);
$logs = $repository->findRecentWithPagination(1, 10);

echo "=== Test du repository HistoriqueLogRepository ===\n";
echo "Nombre de logs trouvés: " . count($logs) . "\n\n";

if (!empty($logs)) {
    echo "Premier log trouvé:\n";
    print_r($logs[0]);
    echo "\n";
}

// Test du contrôleur
$controller = $container->get(App\Controller\Audit\AuditController::class);
$request = Symfony\Component\HttpFoundation\Request::create('/historique/logs/recent?limit=5');
$response = $controller->recentLogs($request, $repository);

echo "=== Test du contrôleur AuditController ===\n";
echo "Response status: " . $response->getStatusCode() . "\n";
echo "Response content:\n";
echo $response->getContent() . "\n";
