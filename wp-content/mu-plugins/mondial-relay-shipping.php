<?php
/**
 * Plugin Name: Custom Mondial Relay & Shipping Manager
 * Description: Recherche de points relais via l'API Mondial Relay et enregistrement du mode de livraison (Domicile vs Point Relais) sur la commande.
 * Version: 1.2
 * Author: Équipe Projet Headless
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. Route REST : recherche de points relais
 *    POST /wp-json/custom/v1/mondial-relay/points-relais
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

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[MR] requête reçue : ' . wp_json_encode($params));
    }

    if (empty($postal_code)) {
        return new WP_Error('missing_cp', 'Le code postal est obligatoire.', ['status' => 400]);
    }

    // Mode simulation (développement, sans identifiants Mondial Relay) :
    // activer avec define('MR_MOCK', true); dans wp-config.php. À retirer en production.
    if (defined('MR_MOCK') && MR_MOCK) {
        return rest_ensure_response([
            'success'       => true,
            'points_relais' => [
                ['Num' => '000001', 'LgAdr1' => 'Tabac Test',    'LgAdr3' => '1 rue de Test',      'CP' => $postal_code, 'Ville' => $city ?: 'Ville Test', 'Distance' => '350'],
                ['Num' => '000002', 'LgAdr1' => 'Relais Test 2', 'LgAdr3' => '5 avenue Exemple',   'CP' => $postal_code, 'Ville' => $city ?: 'Ville Test', 'Distance' => '800'],
                ['Num' => '000003', 'LgAdr1' => 'Boutique Test', 'LgAdr3' => '12 boulevard Modèle', 'CP' => $postal_code, 'Ville' => $city ?: 'Ville Test', 'Distance' => '1200'],
            ],
        ]);
    }

    // Identifiants : définis dans wp-config.php (MR_ENSEIGNE / MR_PRIVATE_KEY).
    // À défaut, on retombe sur les identifiants de TEST officiels Mondial Relay.
    $enseigne    = defined('MR_ENSEIGNE') ? MR_ENSEIGNE : 'BDTEST13';
    $private_key = defined('MR_PRIVATE_KEY') ? MR_PRIVATE_KEY : 'PrivateK';

    $string_to_hash = $enseigne . $country . '' . $city . $postal_code . '' . '' . '' . '' . '' . $action_type . '' . '' . $private_key;
    $security_key   = strtoupper(md5($string_to_hash));

    $soap_url = 'https://api.mondialrelay.com/Web_Services.asmx?WSDL';

    $post_data = [
        'Enseigne'       => $enseigne,
        'Pays'           => $country,
        'Ville'          => $city,
        'CP'             => $postal_code,
        'Taille'         => '',
        'Poids'          => '',
        'Action'         => $action_type,
        'DelaiEnvoi'     => '',
        'RayonRecherche' => '',
        'TypeActivite'   => '',
        'Security'       => $security_key,
    ];

    try {
        if (!class_exists('SoapClient')) {
            return new WP_Error('soap_missing', 'L\'extension PHP SOAP n\'est pas activée sur le serveur.', ['status' => 500]);
        }

        $client   = new SoapClient($soap_url, ['trace' => true, 'exceptions' => true]);
        $response = $client->WSI3_PointRelais_Recherche($post_data);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[MR] réponse SOAP : ' . wp_json_encode($response));
        }

        if (isset($response->WSI3_PointRelais_RechercheResult)) {
            $result = $response->WSI3_PointRelais_RechercheResult;

            if ((int) $result->STAT === 0) {
                return rest_ensure_response([
                    'success'       => true,
                    'points_relais' => $result->PointsRelais->PointRelais_Details ?? [],
                ]);
            }

            return new WP_Error('mondial_relay_error', 'Erreur Mondial Relay (Code: ' . $result->STAT . ')', ['status' => 400]);
        }

        return new WP_Error('mondial_relay_invalid', 'Réponse invalide du service Mondial Relay.', ['status' => 500]);

    } catch (\Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[MR] exception SOAP : ' . $e->getMessage());
        }
        return new WP_Error('soap_error', $e->getMessage(), ['status' => 500]);
    }
}

/**
 * 2. Enregistre le mode de livraison et le point relais dans les métas de la commande.
 *
 * Appelée par le handler de l'endpoint custom/v1/checkout (checkout.php), une
 * fois la commande créée. Le hook woocommerce_checkout_create_order ne se
 * déclenche pas pour un endpoint REST personnalisé, d'où cette fonction.
 *
 * @param WC_Order $order
 * @param array|null $details ['type' => 'relay'|'home', 'method_id', 'relay_id', 'relay_name', 'relay_address']
 */
function custom_save_shipping_details($order, $details) {
    if (!$order || !is_array($details)) {
        return;
    }

    $type = sanitize_text_field($details['type'] ?? 'home');
    $type = in_array($type, ['home', 'relay'], true) ? $type : 'home';

    $order->update_meta_data('_shipping_method_type', $type);

    if (!empty($details['method_id'])) {
        $order->update_meta_data('_shipping_method_id', sanitize_text_field($details['method_id']));
    }

    if ($type === 'relay' && !empty($details['relay_id'])) {
        $order->update_meta_data('_mondial_relay_id', sanitize_text_field($details['relay_id']));
        $order->update_meta_data('_mondial_relay_name', sanitize_text_field($details['relay_name'] ?? ''));
        $order->update_meta_data('_mondial_relay_address', sanitize_text_field($details['relay_address'] ?? ''));
    }

    $order->save();

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[MR] métas enregistrées sur la commande #' . $order->get_id() . ' : ' . wp_json_encode($details));
    }
}
