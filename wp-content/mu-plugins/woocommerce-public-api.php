<?php
/**
 * Plugin Name: WooCommerce Public API
 * Description: Allow public REST API access to WooCommerce products
 * Version: 1.0
 */

add_filter('rest_authentication_errors', function($result) {
	if (! empty($result)) {
		return $result;
	}
	if (is_user_logged_in()) {
		return $result;
	}
	global $wp;
	if (isset($wp->query_vars['rest_route'])) {
		$route = $wp->query_vars['rest_route'];
		if (strpos($route, '/wc/') === 0 && strpos($route, '/products') !== false) {
			return null;
		}
	}
	return $result;
}, 10, 1);
