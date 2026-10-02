<?php

/*=======================================
 *  Endpoint pour créer les commandes via Stripe
 *  Reçoit les adresses et les détails de paiement
 *  =============================================*/

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/checkout', [
        'methods'             => 'POST',
        'callback'            => 'headless_create_order_from_checkout',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Tarifs de livraison Mondial Relay (source de vérité côté serveur).
 * Les ids et les prix doivent correspondre à ceux de CheckoutForm.jsx.
 */
function headless_get_shipping_rates()
{
    return [
        'mr_relay' => [
            'label' => 'Mondial Relay - Point Relais',
            'cost'  => 4.50,
            'type'  => 'relay',
        ],
        'mr_home' => [
            'label' => 'Mondial Relay - Livraison à domicile',
            'cost'  => 7.90,
            'type'  => 'home',
        ],
    ];
}

function headless_create_order_from_checkout($request)
{
    if (!function_exists('wc_create_order')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce est requis.', ['status' => 500]);
    }

    $params = $request->get_json_params();

    $shipping_address = isset($params['shippingAddress']) ? $params['shippingAddress'] : [];
    $billing_address = isset($params['billingAddress']) ? $params['billingAddress'] : [];
    $cart_items = isset($params['cartItems']) ? $params['cartItems'] : [];
    $payment_method_id = isset($params['paymentMethodId']) ? sanitize_text_field($params['paymentMethodId']) : '';

    // Détails de livraison envoyés par le front : { type, method_id, relay_id, relay_name, relay_address }
    $shipping_details = (isset($params['shipping_method_details']) && is_array($params['shipping_method_details']))
        ? $params['shipping_method_details']
        : [];

    if (empty($cart_items)) {
        return new WP_Error('empty_cart', 'Le panier est vide.', ['status' => 400]);
    }

    if (empty($shipping_address['email'])) {
        return new WP_Error('missing_email', 'Email requis.', ['status' => 400]);
    }

    $email = sanitize_email($shipping_address['email']);
    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'Email invalide.', ['status' => 400]);
    }

    // Mode de livraison : on valide l'id côté serveur, le prix et le type viennent
    // de notre table de tarifs, jamais du front.
    // Transition : tant que le front n'envoie pas encore shipping_method_details,
    // la commande est créée sans ligne de livraison, comme avant. Dès que le
    // front l'envoie, il est entièrement contrôlé ici. Une fois le nouveau front
    // en ligne, rendre ce champ obligatoire.
    $rate = null;
    $shipping_method_id = '';

    if (!empty($shipping_details)) {
        $rates = headless_get_shipping_rates();
        $shipping_method_id = sanitize_text_field($shipping_details['method_id'] ?? '');

        if (!isset($rates[$shipping_method_id])) {
            return new WP_Error('invalid_shipping_method', 'Mode de livraison invalide.', ['status' => 400]);
        }

        $rate = $rates[$shipping_method_id];

        if ($rate['type'] === 'relay' && empty($shipping_details['relay_id'])) {
            return new WP_Error('missing_relay', 'Veuillez sélectionner un point relais.', ['status' => 400]);
        }
    }

    // Seule l'identité authentifiée par le jeton JWT compte. Un identifiant lu
    // dans le corps de la requête laisserait n'importe qui rattacher une
    // commande au compte de son choix, et en remplacer les adresses
    // enregistrées — celles que le formulaire de commande pré-remplit.
    $user_id = get_current_user_id();

    // Entre l'ajout au panier et le paiement, la derniere piece d'une taille a
    // pu partir chez un autre client : on refuse la commande plutot que de
    // vendre un article qui n'existe plus. Pour un produit variable, l'id du
    // panier est celui de la variation, le stock controle est donc celui de
    // la taille choisie.
    foreach ($cart_items as $item) {
        $product_id = isset($item['id']) ? intval($item['id']) : 0;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;

        if (!$product_id) continue;

        $product = wc_get_product($product_id);
        if (!$product) continue;

        if (!$product->is_in_stock() || !$product->has_enough_stock($quantity)) {
            return new WP_Error(
                'insufficient_stock',
                sprintf('Stock insuffisant pour « %s ». Veuillez modifier votre panier.', $product->get_name()),
                ['status' => 409]
            );
        }
    }

    $order = wc_create_order();

    // Associer la commande au user connecté si disponible
    if ($user_id > 0) {
        $order->set_customer_id($user_id);
    }

    foreach ($cart_items as $item) {
        $product_id = isset($item['id']) ? intval($item['id']) : 0;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;

        if (!$product_id) continue;

        $product = wc_get_product($product_id);
        if (!$product) continue;

        $order->add_product($product, $quantity);
    }

    if (!empty($shipping_address)) {
        $order->set_address([
            'first_name' => sanitize_text_field($shipping_address['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($shipping_address['last_name'] ?? ''),
            'company'    => sanitize_text_field($shipping_address['company'] ?? ''),
            'address_1'  => sanitize_text_field($shipping_address['address_1'] ?? ''),
            'address_2'  => sanitize_text_field($shipping_address['address_2'] ?? ''),
            'city'       => sanitize_text_field($shipping_address['city'] ?? ''),
            'postcode'   => sanitize_text_field($shipping_address['postcode'] ?? ''),
            'country'    => sanitize_text_field($shipping_address['country'] ?? ''),
            'email'      => $email,
            'phone'      => sanitize_text_field($shipping_address['phone'] ?? ''),
        ], 'shipping');
    }

    if (!empty($billing_address)) {
        $order->set_address([
            'first_name' => sanitize_text_field($billing_address['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($billing_address['last_name'] ?? ''),
            'company'    => sanitize_text_field($billing_address['company'] ?? ''),
            'address_1'  => sanitize_text_field($billing_address['address_1'] ?? ''),
            'address_2'  => sanitize_text_field($billing_address['address_2'] ?? ''),
            'city'       => sanitize_text_field($billing_address['city'] ?? ''),
            'postcode'   => sanitize_text_field($billing_address['postcode'] ?? ''),
            'country'    => sanitize_text_field($billing_address['country'] ?? ''),
            'email'      => $email,
        ], 'billing');
    }

    $order->set_payment_method_title('Stripe');
    $order->set_payment_method('stripe');

    if ($rate) {
        // Ligne de livraison (prix calculé côté serveur)
        $shipping_item = new WC_Order_Item_Shipping();
        $shipping_item->set_method_title($rate['label']);
        $shipping_item->set_method_id($shipping_method_id);
        $shipping_item->set_total($rate['cost']);
        $order->add_item($shipping_item);

        // Métas : type de livraison + point relais choisi (définie dans le mu-plugin Mondial Relay)
        if (function_exists('custom_save_shipping_details')) {
            custom_save_shipping_details($order, [
                'type'          => $rate['type'],
                'method_id'     => $shipping_method_id,
                'relay_id'      => $shipping_details['relay_id'] ?? '',
                'relay_name'    => $shipping_details['relay_name'] ?? '',
                'relay_address' => $shipping_details['relay_address'] ?? '',
            ]);
        }
    }

    $order->calculate_totals();
    $order->save();

    $order_id = $order->get_id();

    // Réduction du stock par WooCommerce lui-même. wc_reduce_stock_levels seul
    // marque chaque ligne (pas de double retrait au passage en Terminée) mais
    // pas la commande : sans ce marquage « stock réduit », WooCommerce ne
    // remet pas le stock quand la commande est annulée. La variante « maybe »
    // fait les deux.
    wc_maybe_reduce_stock_levels($order_id);

    // Sauvegarder les adresses dans le profil du customer si connecté
    if ($user_id > 0) {
        headless_save_customer_addresses_from_order($order, $user_id);
    }

    headless_send_order_confirmation_email($order, $email);
    headless_send_order_notification_to_admin($order);

    return rest_ensure_response([
        'success'  => true,
        'order_id' => $order_id,
        'message' => 'Commande créée avec succès',
    ]);
}

/**
 * Texte décrivant la livraison (utilisé dans les e-mails). Vide pour une
 * commande passée sans mode de livraison (ancien front).
 */
function headless_shipping_summary_text($order)
{
    $type = $order->get_meta('_shipping_method_type');

    if (!$type) {
        return '';
    }

    $text = "Livraison: ";

    if ($type === 'relay') {
        $text .= "Point Relais Mondial Relay\n";
        $text .= "  " . $order->get_meta('_mondial_relay_name') . "\n";
        $text .= "  " . $order->get_meta('_mondial_relay_address') . "\n";
        $text .= "  (n° relais : " . $order->get_meta('_mondial_relay_id') . ")\n";
    } else {
        $text .= "À domicile (Mondial Relay)\n";
    }

    return $text . "\n";
}

function headless_send_order_confirmation_email($order, $customer_email)
{
    $subject = 'Confirmation de votre commande n°' . $order->get_order_number();

    $body = "Bonjour,\n\n";
    $body .= "Merci pour votre commande !\n\n";
    $body .= "Numéro de commande: #" . $order->get_order_number() . "\n";
    $body .= "Date: " . $order->get_date_created()->date('d/m/Y H:i') . "\n";
    $body .= "Total: " . $order->get_formatted_order_total() . "\n\n";

    $body .= headless_shipping_summary_text($order);

    $body .= "Articles:\n";
    foreach ($order->get_items() as $item) {
        $body .= "- " . $item->get_name() . " x " . $item->get_quantity() . " = " . wc_price($item->get_total()) . "\n";
    }

    $body .= "\nNous vous remerçions de votre confiance.\n";
    $body .= "Cordialement,\nL'équipe";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    wp_mail($customer_email, $subject, $body, $headers);
}

function headless_save_customer_addresses_from_order($order, $user_id)
{
    if (!class_exists('WC_Customer')) {
        return;
    }

    $customer = new WC_Customer($user_id);

    // Récupérer les adresses de la commande
    $shipping = $order->get_address('shipping');
    $billing = $order->get_address('billing');

    // Mettre à jour l'adresse de livraison si elle existe dans la commande
    if (!empty($shipping)) {
        if (isset($shipping['first_name'])) $customer->set_shipping_first_name(sanitize_text_field($shipping['first_name']));
        if (isset($shipping['last_name'])) $customer->set_shipping_last_name(sanitize_text_field($shipping['last_name']));
        if (isset($shipping['company'])) $customer->set_shipping_company(sanitize_text_field($shipping['company']));
        if (isset($shipping['address_1'])) $customer->set_shipping_address_1(sanitize_text_field($shipping['address_1']));
        if (isset($shipping['address_2'])) $customer->set_shipping_address_2(sanitize_text_field($shipping['address_2']));
        if (isset($shipping['city'])) $customer->set_shipping_city(sanitize_text_field($shipping['city']));
        if (isset($shipping['postcode'])) $customer->set_shipping_postcode(sanitize_text_field($shipping['postcode']));
        if (isset($shipping['country'])) $customer->set_shipping_country(sanitize_text_field(strtoupper($shipping['country'])));
        if (isset($shipping['phone'])) $customer->set_shipping_phone(sanitize_text_field($shipping['phone']));
    }

    // Mettre à jour l'adresse de facturation si elle existe dans la commande
    if (!empty($billing)) {
        if (isset($billing['first_name'])) $customer->set_billing_first_name(sanitize_text_field($billing['first_name']));
        if (isset($billing['last_name'])) $customer->set_billing_last_name(sanitize_text_field($billing['last_name']));
        if (isset($billing['company'])) $customer->set_billing_company(sanitize_text_field($billing['company']));
        if (isset($billing['address_1'])) $customer->set_billing_address_1(sanitize_text_field($billing['address_1']));
        if (isset($billing['address_2'])) $customer->set_billing_address_2(sanitize_text_field($billing['address_2']));
        if (isset($billing['city'])) $customer->set_billing_city(sanitize_text_field($billing['city']));
        if (isset($billing['postcode'])) $customer->set_billing_postcode(sanitize_text_field($billing['postcode']));
        if (isset($billing['country'])) $customer->set_billing_country(sanitize_text_field(strtoupper($billing['country'])));
        if (isset($billing['phone'])) $customer->set_billing_phone(sanitize_text_field($billing['phone']));
    }

    $customer->save();
}

function headless_send_order_notification_to_admin($order)
{
    $admin_email = get_option('admin_email');
    $subject = 'Nouvelle commande reçue: #' . $order->get_order_number();

    $body = "Une nouvelle commande a été reçue.\n\n";
    $body .= "Numéro de commande: #" . $order->get_order_number() . "\n";
    $body .= "Client: " . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . "\n";
    $body .= "Email: " . $order->get_billing_email() . "\n";
    $body .= "Total: " . $order->get_formatted_order_total() . "\n\n";

    $body .= headless_shipping_summary_text($order);

    $body .= "Afficher la commande: " . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . "\n";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    wp_mail($admin_email, $subject, $body, $headers);
}
