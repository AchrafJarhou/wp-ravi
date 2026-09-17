<?php
/**
 * Email Test Endpoint
 * POST /wp-json/custom/v1/email-test
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function() {
    register_rest_route('custom/v1', '/email-test', array(
        'methods' => 'POST',
        'callback' => function($request) {
            $email = $request->get_param('email') ?: 'achraf.jarhou@laplateforme.io';

            error_log('🧪 TEST EMAIL: Attempting to send to ' . $email);

            $result = wp_mail(
                $email,
                'Test Email from Ravi',
                'This is a test email to verify SMTP configuration.'
            );

            if ($result) {
                error_log('✅ TEST EMAIL: Successfully sent to ' . $email);
                return array('success' => true, 'message' => 'Email sent to ' . $email);
            } else {
                error_log('❌ TEST EMAIL: Failed to send to ' . $email);
                return array('success' => false, 'message' => 'Failed to send email');
            }
        },
        'permission_callback' => '__return_true'
    ));
});
