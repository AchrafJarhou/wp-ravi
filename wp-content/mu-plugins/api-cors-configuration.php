<?php

/*=======================================
 *  Configuration CORS pour l'API REST
 *  Permet aux domaines autorisés d'accéder à l'API
 *  =============================================*/

if (!defined('ABSPATH')) {
    exit;
}

// Activer le CORS pour l'API REST (WordPress gère les headers automatiquement)
add_filter('rest_send_cors_headers', '__return_true');

// Autoriser les origines listées
add_filter('allowed_http_origins', function ($origins) {
    $origins[] = 'http://localhost:5173';
    $origins[] = 'http://127.0.0.1:5173';
    $origins[] = 'http://10.60.4.51:5173';
    $origins[] = 'https://template-woo-commerce-headless-3jh7wv8rz.vercel.app';
    return $origins;
});

// Autoriser les headers CORS
add_filter('rest_allowed_cors_headers', function ($headers) {
    $headers[] = 'Authorization';
    return $headers;
});
