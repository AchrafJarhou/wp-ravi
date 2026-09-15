<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'NOT SET',
]);
?>
