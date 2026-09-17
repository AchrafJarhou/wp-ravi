<?php
/**
 * Brevo Diagnostic Endpoint
 * Tests Brevo API directly, bypassing WordPress mail system
 * POST /wp-json/custom/v1/brevo-test
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function() {
    register_rest_route('custom/v1', '/brevo-test', array(
        'methods' => 'POST',
        'callback' => function($request) {
            $email = $request->get_param('email') ?: 'achraf.jarhou@laplateforme.io';

            // Log to uploads directory
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/brevo-diagnostic.log';
            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] Test email to: ' . $email . "\n", FILE_APPEND);

            // Check if BREVO_API_KEY is defined
            if (!defined('BREVO_API_KEY') || empty(BREVO_API_KEY)) {
                @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] BREVO_API_KEY not defined!' . "\n", FILE_APPEND);
                return array(
                    'success' => false,
                    'message' => 'BREVO_API_KEY not configured',
                    'brevo_key_defined' => false
                );
            }

            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] BREVO_API_KEY defined, calling API' . "\n", FILE_APPEND);

            // Call Brevo API directly
            $body = array(
                'sender' => array(
                    'email' => 'jarhou06@gmail.com',
                    'name' => 'Ravi'
                ),
                'to' => array(
                    array('email' => $email)
                ),
                'subject' => 'Test Email from Brevo',
                'htmlContent' => 'This is a direct Brevo API test email.'
            );

            $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', array(
                'method' => 'POST',
                'headers' => array(
                    'api-key' => BREVO_API_KEY,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($body),
                'timeout' => 10
            ));

            if (is_wp_error($response)) {
                $error = $response->get_error_message();
                @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] WP Error: ' . $error . "\n", FILE_APPEND);
                return array(
                    'success' => false,
                    'message' => 'WP Error: ' . $error,
                    'error' => true
                );
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body_content = wp_remote_retrieve_body($response);

            @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] HTTP ' . $http_code . ': ' . $body_content . "\n", FILE_APPEND);

            if ($http_code === 201) {
                return array(
                    'success' => true,
                    'message' => 'Email sent to ' . $email . ' via Brevo API',
                    'http_code' => $http_code,
                    'brevo_key_defined' => true
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Brevo API error',
                    'http_code' => $http_code,
                    'response' => $body_content,
                    'brevo_key_defined' => true
                );
            }
        },
        'permission_callback' => '__return_true'
    ));
});
