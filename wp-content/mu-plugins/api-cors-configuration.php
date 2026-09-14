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

    // En production (remplace par ton domaine)
    // $origins[] = 'https://mondomaine.com';

    return $origins;
});

// Autoriser les credentials (cookies, tokens)
add_filter('rest_allowed_cors_headers', function ($allowed_headers) {
    $allowed_headers[] = 'Authorization';
    return $allowed_headers;
});
