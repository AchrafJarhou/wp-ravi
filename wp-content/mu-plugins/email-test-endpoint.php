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

            // Check if Brevo sent it (even if wp_mail returns false due to SMTP failure)
            $brevo_sent = !empty($GLOBALS['brevo_mail_sent']);

            if ($result || $brevo_sent) {
                error_log('✅ TEST EMAIL: Successfully sent to ' . $email . ' (Brevo: ' . ($brevo_sent ? 'yes' : 'no') . ')');
                return array('success' => true, 'message' => 'Email sent to ' . $email . ' via Brevo', 'brevo_sent' => $brevo_sent);
            } else {
                error_log('❌ TEST EMAIL: Failed to send to ' . $email);
                return array('success' => false, 'message' => 'Failed to send email', 'brevo_sent' => false);
            }
        },
        'permission_callback' => '__return_true'
    ));
});
