<?php

/*=======================================
 *  Enrichissement de la réponse JWT token
 *  Ajoute les données user (first_name, last_name) à la réponse
 *  du plugin jwt-auth standard pour le headless frontend
 *  =============================================*/

add_filter('jwt_auth_token_before_dispatch', function ($response, $user) {
    if ($user && is_a($user, 'WP_User')) {
        $response['first_name'] = get_user_meta($user->ID, 'first_name', true);
        $response['last_name'] = get_user_meta($user->ID, 'last_name', true);
    }
    return $response;
}, 10, 2);
