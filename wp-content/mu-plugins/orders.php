<?php

/*=======================================
 *  wp-json/wc/v3/orders exige les cles API WooCommerce (consumer_key /
 *  consumer_secret), reservees aux admins/gestionnaires de boutique.
 *  Un client connecte via JWT n'a pas la capacite requise (edit_others_shop_orders)
 *  et se prend un 401 sur cette route.
 *  On expose donc une route custom, utilisable avec le JWT du user, qui ne
 *  renvoie QUE les commandes du user actuellement connecte.
 *  =============================================*/

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/orders', [
        'methods'             => 'GET',
        'callback'            => 'headless_get_current_user_orders',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});

function headless_get_current_user_orders($request)
{
    if (!function_exists('wc_get_orders')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce est requis pour cette fonctionnalite.', ['status' => 500]);
    }

    $orders = wc_get_orders([
        'customer_id' => get_current_user_id(),
        'limit'       => -1,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);

    $data = array_map(function ($order) {
        return [
            'id'       => $order->get_id(),
            'number'   => $order->get_order_number(),
            'status'   => $order->get_status(),
            'date'     => $order->get_date_created() ? $order->get_date_created()->date('c') : null,
            'total'    => $order->get_total(),
            'currency' => $order->get_currency(),
            'items'    => array_map(function ($item) {
                $product = $item->get_product();
                $image_url = '';

                if ($product) {
                    $image_id = $product->get_image_id();
                    if ($image_id) {
                        $image_url = wp_get_attachment_image_src($image_id, 'medium')[0];
                    }
                }

                return [
                    'id'       => $item->get_id(),
                    'name'     => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total'    => $item->get_total(),
                    'image'    => ['src' => $image_url],
                ];
            }, array_values($order->get_items())),
        ];
    }, $orders);

    return rest_ensure_response($data);
}
