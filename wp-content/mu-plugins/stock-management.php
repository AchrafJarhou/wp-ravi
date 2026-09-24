<?php

/*=======================================
 *  Gestion du stock des produits
 *  - Endpoints API pour admin
 *  - Fonctions utilitaires pour décrémenter le stock
 *  =============================================*/

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/products/(?P<id>[\w-]+)/stock', [
        'methods'             => 'GET',
        'callback'            => 'headless_get_product_stock',
        'permission_callback' => 'headless_check_admin_capability',
    ]);

    register_rest_route('custom/v1', '/products/(?P<id>[\w-]+)/stock', [
        'methods'             => 'POST',
        'callback'            => 'headless_update_product_stock',
        'permission_callback' => 'headless_check_admin_capability',
    ]);
});

function headless_check_admin_capability()
{
    return current_user_can('manage_woocommerce_products');
}

function headless_get_product_stock($request)
{
    if (!function_exists('wc_get_product')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce requis.', ['status' => 500]);
    }

    $product_id = headless_resolve_product_id($request->get_param('id'));

    if (!$product_id) {
        return new WP_Error('product_not_found', 'Produit introuvable.', ['status' => 404]);
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return new WP_Error('product_not_found', 'Produit introuvable.', ['status' => 404]);
    }

    return rest_ensure_response([
        'product_id' => $product_id,
        'name'       => $product->get_name(),
        'stock_qty'  => $product->get_stock_quantity(),
        'is_in_stock' => $product->is_in_stock(),
        'manage_stock' => $product->get_manage_stock(),
    ]);
}

function headless_update_product_stock($request)
{
    if (!function_exists('wc_get_product')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce requis.', ['status' => 500]);
    }

    $product_id = headless_resolve_product_id($request->get_param('id'));

    if (!$product_id) {
        return new WP_Error('product_not_found', 'Produit introuvable.', ['status' => 404]);
    }

    $params = $request->get_json_params();
    $new_stock = isset($params['stock_qty']) ? intval($params['stock_qty']) : null;

    if ($new_stock === null) {
        return new WP_Error('invalid_stock', 'stock_qty requis.', ['status' => 400]);
    }

    if ($new_stock < 0) {
        return new WP_Error('invalid_stock', 'Le stock ne peut pas être négatif.', ['status' => 400]);
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return new WP_Error('product_not_found', 'Produit introuvable.', ['status' => 404]);
    }

    $product->set_stock_quantity($new_stock);
    $product->save();

    return rest_ensure_response([
        'success'    => true,
        'product_id' => $product_id,
        'name'       => $product->get_name(),
        'stock_qty'  => $product->get_stock_quantity(),
        'is_in_stock' => $product->is_in_stock(),
    ]);
}

if (!function_exists('headless_resolve_product_id')) {
    function headless_resolve_product_id($raw_id)
    {
        if (is_numeric($raw_id)) {
            return (int) $raw_id;
        }

        $query = new WP_Query([
            'name'           => sanitize_title($raw_id),
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        return $query->have_posts() ? (int) $query->posts[0] : 0;
    }
}

function headless_decrement_product_stock($product_id, $quantity)
{
    if (!function_exists('wc_get_product')) {
        return false;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return false;
    }

    $current_stock = $product->get_stock_quantity();
    $new_stock = max(0, $current_stock - $quantity);

    $product->set_stock_quantity($new_stock);
    $product->save();

    return true;
}
