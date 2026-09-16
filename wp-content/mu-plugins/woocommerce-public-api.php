<?php
/**
 * Plugin Name: WooCommerce Public API
 * Description: Allow public REST API access to WooCommerce products
 * Version: 1.1
 */

add_filter('rest_authentication_errors', function($error) {
	// If user is logged in, always allow
	if (is_user_logged_in()) {
		return $error;
	}

	// Get the REST request
	if (function_exists('rest_get_server')) {
		$server = rest_get_server();
		$request = $server->last_request;

		if ($request) {
			$route = $request->get_route();
			$method = $request->get_method();

			// Allow public GET access to WooCommerce products
			if (preg_match('#^/wc(-admin)?/.*products#', $route) && $method === 'GET') {
				return null;
			}
		}
	}

	return $error;
}, 15, 1);
