<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'script' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
]);
?>
