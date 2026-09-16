<?php
/**
 * Plugin Name: Public WooCommerce API
 * Description: Register public WooCommerce products endpoint
 * Version: 1.0
 */

add_action('rest_api_init', function() {
	register_rest_route('custom/v1', '/products', array(
		'methods' => 'GET',
		'callback' => function($request) {
			$args = array(
				'limit' => $request->get_param('limit') ?: 100,
				'paged' => $request->get_param('page') ?: 1,
				'orderby' => $request->get_param('orderby') ?: 'date',
				'order' => $request->get_param('order') ?: 'DESC',
			);

			$products = wc_get_products($args);
			$data = array();

			foreach ($products as $product) {
				$data[] = array(
					'id' => $product->get_id(),
					'name' => $product->get_name(),
					'price' => $product->get_price(),
					'description' => $product->get_short_description(),
					'image' => wp_get_attachment_url($product->get_image_id()),
					'url' => $product->get_permalink(),
				);
			}

			return new WP_REST_Response($data, 200);
		},
		'permission_callback' => '__return_true'
	));
});
