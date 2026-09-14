<?php

/*=======================================
 *  Wishlist : liste de produits favoris par utilisateur, stockee en user meta
 *  (pas d'equivalent natif dans le Store API, contrairement au panier qui
 *  repose sur une session invite). Necessite donc un compte connecte (JWT).
 *  =============================================*/

const HEADLESS_WISHLIST_META_KEY = '_headless_wishlist';

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/wishlist', [
        'methods'             => 'GET',
        'callback'            => 'headless_get_wishlist',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    register_rest_route('custom/v1', '/wishlist', [
        'methods'             => 'POST',
        'callback'            => 'headless_add_to_wishlist',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    register_rest_route('custom/v1', '/wishlist', [
        'methods'             => 'DELETE',
        'callback'            => 'headless_remove_from_wishlist',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});

function headless_get_wishlist_ids($user_id)
{
    $ids = get_user_meta($user_id, HEADLESS_WISHLIST_META_KEY, true);
    return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
}

function headless_set_wishlist_ids($user_id, $ids)
{
    update_user_meta($user_id, HEADLESS_WISHLIST_META_KEY, array_values(array_unique(array_map('intval', $ids))));
}

// Reutilise la route /custom/v1/products/{id} (deja enrichie avec le stock des
// variations) pour renvoyer aux favoris le meme format d'objet produit que le
// reste du front (catalogue, panier).
function headless_get_wishlist($request)
{
    if (!function_exists('wc_get_product')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce est requis pour cette fonctionnalite.', ['status' => 500]);
    }

    $user_id     = get_current_user_id();
    $product_ids = headless_get_wishlist_ids($user_id);
    $products    = [];

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_status() !== 'publish') {
            continue;
        }

        $sub_request  = new WP_REST_Request('GET', '/custom/v1/products/' . $product_id);
        $sub_response = rest_do_request($sub_request);

        if (!$sub_response->is_error()) {
            $products[] = $sub_response->get_data();
        }
    }

    return rest_ensure_response($products);
}

function headless_add_to_wishlist($request)
{
    if (!function_exists('wc_get_product')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce est requis pour cette fonctionnalite.', ['status' => 500]);
    }

    $product_id = (int) $request->get_param('productId');
    $product    = $product_id ? wc_get_product($product_id) : null;

    if (!$product) {
        return new WP_Error('product_not_found', 'Produit introuvable.', ['status' => 404]);
    }

    $user_id = get_current_user_id();
    $ids     = headless_get_wishlist_ids($user_id);

    if (!in_array($product_id, $ids, true)) {
        $ids[] = $product_id;
        headless_set_wishlist_ids($user_id, $ids);
    }

    return headless_get_wishlist($request);
}

function headless_remove_from_wishlist($request)
{
    $product_id = (int) $request->get_param('productId');
    $user_id    = get_current_user_id();
    $ids        = headless_get_wishlist_ids($user_id);

    headless_set_wishlist_ids($user_id, array_diff($ids, [$product_id]));

    return headless_get_wishlist($request);
}
