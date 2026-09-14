<?php
add_filter('rest_index', function ($response) {
    $data = $response->get_data();

    // On injecte les infos WooCommerce dans la réponse globale
    $data['store_email'] = get_option('woocommerce_email_from_address');
    $data['store_name']  = get_option('woocommerce_email_from_name');

    $response->set_data($data);
    return $response;
});

// 1. Utiliser l'adresse "De" configurée dans WooCommerce pour TOUS les mails du site
add_filter('wp_mail_from', function ($original_email) {
    $wc_email = get_option('woocommerce_email_from_address');

    // Si l'option WooCommerce existe et n'est pas vide, on l'utilise
    return !empty($wc_email) ? $wc_email : $original_email;
});

// 2. Utiliser le nom "De" configuré dans WooCommerce
add_filter('wp_mail_from_name', function ($original_name) {
    $wc_name = get_option('woocommerce_email_from_name');

    return !empty($wc_name) ? $wc_name : get_bloginfo('name');
});
