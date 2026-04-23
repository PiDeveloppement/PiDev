<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=pidev;charset=utf8mb4', 'root', 'manaimanai');
$tables = ['event','event_ticket','event_category','budget','depense'];
foreach ($tables as $t) {
    echo "== $t ==\n";
    try {
        $q = $pdo->query('DESCRIBE ' . $t);
        foreach ($q as $r) {
            echo $r['Field'] . '|' . $r['Type'] . "\n";
        }
    } catch (Throwable $e) {
        echo "(missing)\n";
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> origin/user
