<?php

// Test simple de l'API avec curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/historique/logs/recent?limit=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "=== Test API /historique/logs/recent ===\n";
echo "HTTP Code: $httpCode\n";
echo "Response:\n$response\n";

if ($error) {
    echo "CURL Error: $error\n";
}
