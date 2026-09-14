<?php

/*=======================================
 *  Configuration CORS pour l'API REST
 *  Permet aux domaines autorisés d'accéder à l'API
 *  =============================================*/

if (!defined('ABSPATH')) {
    exit;
}

add_filter('allowed_http_origins', function ($origins) {
    // En développement local
    $origins[] = 'http://localhost:5173';
    $origins[] = 'http://127.0.0.1:5173';

    // Sur le réseau local (IP locale)
    // Remplace 192.168.1.* par ton réseau
    $origins[] = 'http://192.168.1.*';
    $origins[] = 'http://192.168.*';

    // Frontend Vercel (production)
    $origins[] = 'https://template-woo-commerce-headless-3jh7wv8rz.vercel.app';

    return $origins;
});

// Autoriser les credentials (cookies, tokens)
add_filter('rest_allowed_cors_headers', function ($allowed_headers) {
    $allowed_headers[] = 'Authorization';
    return $allowed_headers;
});

// Ajouter les headers CORS HTTP directement
add_action('init', function() {
    $allowed_origins = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://10.60.4.51:5173',
        'https://template-woo-commerce-headless-3jh7wv8rz.vercel.app',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}, 0);
