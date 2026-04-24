<?php

// Test simple avec connexion directe à la base de données
try {
    // Connexion à la base de données
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=pidev;charset=utf8mb4',
        'root',
        'souhail123',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "=== Test de connexion à la base de données ===\n";
    echo "Connexion réussie!\n\n";

    // Test de la requête SQL utilisée dans le repository
    $sql = "SELECT hl.*, COALESCE(CONCAT(um.First_Name, ' ', um.Last_Name), 'Utilisateur inconnu') as user_name, COALESCE(um.Email, '') as user_email 
            FROM historique_logs hl 
            LEFT JOIN user_model um ON hl.user_id = um.Id_User 
            ORDER BY hl.created_at DESC 
            LIMIT 5";

    echo "=== Test de la requête SQL ===\n";
    echo "Requête: $sql\n\n";

    $stmt = $pdo->query($sql);
    $logs = $stmt->fetchAll();

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
                'changes' => null,
                'createdAt' => $log['created_at'],
                'ipAddress' => $log['ip_address'],
                'userAgent' => $log['user_agent'],
                'userName' => $log['user_name'],
                'userEmail' => $log['user_email'],
                'description' => $log['resource_name'] . ' ' . ($log['action'] === 'CREATE' ? 'Création' : ($log['action'] === 'UPDATE' ? 'Modification' : 'Suppression')) . ' par ' . $log['user_name']
            ];
        }

        echo "=== Simulation de la réponse JSON ===\n";
        $response = [
            'logs' => $logsData,
            'total' => count($logsData)
        ];
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Aucun log trouvé dans la base de données.\n";
    }

} catch (PDOException $e) {
    echo "Erreur de base de données: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
