<?php
/**
 * CORS Configuration for REST API
 * Sends Access-Control-Allow-Origin header for allowed origins
 */

if (!defined('ABSPATH')) {
    exit;
}

// List of allowed origins
$allowed_origins = array(
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://10.60.4.51:5173',
    'https://template-woo-commerce-headless-3jh7wv8rz.vercel.app',
);

// Hook VERY early - at plugins_loaded priority 0 which runs AFTER mu-plugins are loaded
add_action('plugins_loaded', function() use ($allowed_origins) {
    // Only for REST API requests
    if (defined('REST_REQUEST') && REST_REQUEST) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

        if ($origin && in_array($origin, $allowed_origins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin, true);
        }
    }
}, 0);

// Also try with rest_api_init
add_action('rest_api_init', function() use ($allowed_origins) {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

    if ($origin && in_array($origin, $allowed_origins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin, true);
    }
}, 0);

// And via the WordPress CORS filter which WordPress actually checks
add_filter('rest_allowed_cors_origins', function($origins) use ($allowed_origins) {
    return array_unique(array_merge($origins, $allowed_origins));
}, 10, 1);

// Ensure WordPress sends CORS headers
add_filter('rest_send_cors_headers', '__return_true');
