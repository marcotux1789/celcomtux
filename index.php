<?php
// API para dashboard - Versión simplificada
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = [
    'status' => 'ok',
    'message' => 'API funcionando correctamente',
    'endpoints' => [
        'ping' => 'index.php?action=ping',
        'test' => 'index.php?action=test'
    ]
];

echo json_encode($response);
