<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'NOT SET';
$allowed_origins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://10.60.4.51:5173',
    'https://template-woo-commerce-headless.vercel.app',
    'https://template-woo-commerce-headless.vercel.app/',
    'https://template-woo-commerce-headless-3jh7wv8rz.vercel.app',
    'https://template-woo-commerce-headless-3jh7wv8rz.vercel.app/',
];

$is_allowed = in_array($origin, $allowed_origins, true);

// Get some important headers from $_SERVER
$important_headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $important_headers[$key] = $value;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'origin' => $origin,
    'is_allowed' => $is_allowed,
    'allowed_origins' => $allowed_origins,
    'method' => $_SERVER['REQUEST_METHOD'],
    'server_headers' => $important_headers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
