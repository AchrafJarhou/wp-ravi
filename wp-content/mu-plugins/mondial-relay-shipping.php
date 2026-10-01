<?php
/**
 * Plugin Name: Custom Mondial Relay & Shipping Manager
 * Description: Gère la recherche de points relais via l'API Mondial Relay et l'enregistrement des modes de livraison (Domicile vs Point Relais).
 * Version: 1.0
 * Author: Équipe Projet Headless
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. Route REST pour la recherche de points relais (Mondial Relay)
 */
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/mondial-relay/points-relais', [
        'methods'             => 'POST',
        'callback'            => 'custom_proxy_mondial_relay_search',
        'permission_callback' => '__return_true',
    ]);
});

function custom_proxy_mondial_relay_search($request) {
    $params = $request->get_json_params();

    $postal_code = sanitize_text_field($params['cp'] ?? '');
    $country     = sanitize_text_field($params['country'] ?? 'FR');
    $city        = sanitize_text_field($params['city'] ?? '');
    $action_type = sanitize_text_field($params['action'] ?? '24R');

    if (empty($postal_code)) {
        return new WP_Error('missing_cp', 'Le code postal est obligatoire.', ['status' => 400]);
    }

    // Identifiants de test officiels Mondial Relay
    $enseigne    = 'BDTEST13'; 
    $private_key = 'PrivateK';

    $string_to_hash = $enseigne . $country . '' . $city . $postal_code . '' . '' . '' . '' . '' . $action_type . '' . '' . $private_key;
    $security_key   = strtoupper(md5($string_to_hash));

    $soap_url = 'https://api.mondialrelay.com/Web_Services.asmx?WSDL';
    
    $post_data = [
        'Enseigne'      => $enseigne,
        'Pays'          => $country,
        'Ville'         => $city,
        'CP'            => $postal_code,
        'Taille'        => '',
        'Poids'         => '',
        'Action'        => $action_type,
        'DelaiEnvoi'    => '',
        'RayonRecherche'=> '',
        'TypeActivite'  => '',
        'Security'      => $security_key,
    ];

    try {
        if (!class_exists('SoapClient')) {
            return new WP_Error('soap_missing', 'L\'extension PHP SOAP n\'est pas activée sur le serveur.', ['status' => 500]);
        }

        $client   = new SoapClient($soap_url, ['trace' => true, 'exceptions' => true]);
        $response = $client->WSI3_PointRelais_Recherche($post_data);

        if (isset($response->WSI3_PointRelais_RechercheResult)) {
            $result = $response->WSI3_PointRelais_RechercheResult;
            
            if ($result->STAT == 0) {
                return rest_ensure_response([
                    'success'       => true,
                    'points_relais' => $result->PointsRelais->PointRelais_Details ?? []
                ]);
            } else {
                return new WP_Error('mondial_relay_error', 'Erreur Mondial Relay (Code: ' . $result->STAT . ')', ['status' => 400]);
            }
        }

        return new WP_Error('mondial_relay_invalid', 'Réponse invalide du service Mondial Relay.', ['status' => 500]);

    } catch (\Exception $e) {
        return new WP_Error('soap_error', $e->getMessage(), ['status' => 500]);
    }
}

/**
 * 2. Enregistrement du mode de livraison (Domicile ou Point Relais) lors de la commande
 * Permet de stocker le choix du client et les infos du point relais dans les métas WooCommerce
 */
add_action('woocommerce_checkout_create_order', function ($order, $data) {
    // On récupère les données envoyées depuis le front-end React lors du checkout
    $request_data = WC()->session ? WC()->session->get('custom_shipping_data') : null;

    if (!$request_data && isset($_POST['shipping_method_details'])) {
        $request_data = json_decode(sanitize_text_field(wp_unslash($_POST['shipping_method_details'])), true);
    }

    if ($request_data) {
        $method_type   = sanitize_text_field($request_data['type'] ?? 'home'); // 'home' ou 'relay'
        $order->update_meta_data('_shipping_method_type', $method_type);

        if ($method_type === 'relay' && !empty($request_data['relay_id'])) {
            $order->update_meta_data('_mondial_relay_id', sanitize_text_field($request_data['relay_id']));
            $order->update_meta_data('_mondial_relay_name', sanitize_text_field($request_data['relay_name'] ?? ''));
            $order->update_meta_data('_mondial_relay_address', sanitize_text_field($request_data['relay_address'] ?? ''));
        }
    }
}, 10, 2);