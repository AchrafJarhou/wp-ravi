<?php
/*
Plugin Name: Custom Stripe Public Key Endpoint
Description: Expose la clé publique Stripe de WooCommerce pour le frontend Headless
*/

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/stripe', [
        'methods'             => 'GET',
        'callback'            => 'get_headless_stripe_public_key',
        'permission_callback' => '__return_true',
    ]);
});

function get_headless_stripe_public_key()
{
    $stripe_settings = get_option('woocommerce_stripe_settings');
    if (empty($stripe_settings)) {
        return new WP_Error('no_stripe_settings', 'Extension Stripe non configurée.', ['status' => 404]);
    }
    $testmode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
    $publishable_key = $testmode
        ? ($stripe_settings['test_publishable_key'] ?? '')
        : ($stripe_settings['publishable_key'] ?? '');
    if (empty($publishable_key)) {
        return new WP_Error('no_key_found', 'Aucune clé publique configurée dans WooCommerce.', ['status' => 404]);
    }
    return rest_ensure_response([
        'publishable_key' => $publishable_key,
        'testmode'        => $testmode,
    ]);
}
