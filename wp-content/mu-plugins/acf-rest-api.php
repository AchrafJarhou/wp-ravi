<?php
/**
 * Plugin Name: ACF REST API
 * Description: Expose les champs ACF à l'API REST
 * Version: 1.0
 */

function expose_acf_to_rest() {
  // Route debug pour voir tous les metadata
  register_rest_route('acf/v1', '/debug/(?P<post_id>\d+)', array(
    'methods' => 'GET',
    'callback' => function($request) {
      $post_id = $request['post_id'];
      return get_post_meta($post_id);
    },
    'permission_callback' => '__return_true'
  ));
}

function add_acf_to_rest_response($response, $post, $request) {
  $post_id = $post->ID;

  $acf_data = array(
    'hero_video' => wp_get_attachment_url(get_post_meta($post_id, 'hero_video', true)) ?: null,
    'site_logo_custom' => wp_get_attachment_url(get_post_meta($post_id, 'site_logo_custom', true)) ?: null,
    'hero_title' => get_post_meta($post_id, 'hero_title', true)
  );

  $response->data['acf'] = array_filter($acf_data);
  return $response;
}

add_action('rest_api_init', 'expose_acf_to_rest');
add_filter('rest_prepare_page', 'add_acf_to_rest_response', 10, 3);
