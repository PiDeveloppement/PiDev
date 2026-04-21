
<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=pidev', 'root', '');
    $result = $pdo->query('SHOW TABLES');
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables existantes:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
?>

