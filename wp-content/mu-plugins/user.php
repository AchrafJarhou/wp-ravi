<?php

/*=======================================
 *  Suppression de compte :
 *  - Exige le mot de passe actuel en confirmation (une requete DELETE seule,
 *    avec juste un token JWT eventuellement vole/traine dans un onglet, ne
 *    doit pas suffire a effacer definitivement un compte).
 *  - Les commandes ne sont PAS supprimees mais anonymisees
 *  =============================================*/

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/user', [
        'methods'             => 'DELETE',
        'callback'            => 'headless_delete_current_user',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});

function headless_delete_current_user($request)
{
    if (!class_exists('WooCommerce')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce est requis pour cette fonctionnalité.', ['status' => 500]);
    }

    // Requis pour pouvoir utiliser la fonction wp_delete_user() dans l'API REST
    require_once ABSPATH . 'wp-admin/includes/user.php';

    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error('unauthorized', 'Utilisateur non identifié.', ['status' => 401]);
    }

    $user     = get_userdata($user_id);
    $password = (string) $request->get_param('password');

    if (empty($password) || !wp_check_password($password, $user->user_pass, $user_id)) {
        return new WP_Error('invalid_password', 'Mot de passe incorrect. Suppression annulée.', ['status' => 403]);
    }

    // 1. Anonymisation des commandes (on garde l'historique comptable, on retire les données personnelles)
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'limit'       => -1,
    ]);

    foreach ($orders as $order) {
        headless_anonymize_order($order);
    }

    // 2. Suppression du compte utilisateur WordPress
    $deleted = wp_delete_user($user_id);

    if (!$deleted) {
        return new WP_Error('delete_failed', 'Impossible de supprimer le compte utilisateur.', ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'message' => 'Le compte utilisateur a été supprimé. Les commandes sont conservées de façon anonymisée pour les obligations comptables.',
    ]);
}

function headless_anonymize_order($order)
{
    // Reutilise l'anonymiseur natif de WooCommerce  plutot que de reinventer la logique.
    if (class_exists('WC_Privacy_Erasers') && method_exists('WC_Privacy_Erasers', 'remove_order_personal_data')) {
        WC_Privacy_Erasers::remove_order_personal_data($order);
        return;
    }

    // Repli manuel si la classe n'est pas chargee pour une raison ou une autre
    $anonymous = __('Compte supprimé', 'woocommerce');

    $order->set_customer_id(0);
    $order->set_billing_first_name($anonymous);
    $order->set_billing_last_name('');
    $order->set_billing_company('');
    $order->set_billing_address_1('');
    $order->set_billing_address_2('');
    $order->set_billing_city('');
    $order->set_billing_state('');
    $order->set_billing_postcode('');
    $order->set_billing_country('');
    $order->set_billing_phone('');
    $order->set_billing_email('');
    $order->set_shipping_first_name($anonymous);
    $order->set_shipping_last_name('');
    $order->set_shipping_company('');
    $order->set_shipping_address_1('');
    $order->set_shipping_address_2('');
    $order->set_shipping_city('');
    $order->set_shipping_state('');
    $order->set_shipping_postcode('');
    $order->set_shipping_country('');
    $order->set_customer_ip_address('');
    $order->set_customer_user_agent('');
    $order->save();
}
