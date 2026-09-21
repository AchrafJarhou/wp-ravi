<?php
/**
 * Brevo Email Mailer for Production
 * Uses Brevo API - simple, no DNS required
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helper function to get debug log path
function brevo_get_log_path() {
    static $log_path = null;
    if ($log_path === null) {
        $upload_dir = wp_upload_dir();
        $log_path = $upload_dir['basedir'] . '/brevo-mailer.log';
    }
    return $log_path;
}

// Only activate if BREVO_API_KEY is configured
if (!defined('BREVO_API_KEY') || empty(BREVO_API_KEY)) {
    return;
}

// Production: Use Brevo API
add_filter('wp_mail', function($atts) {
    try {
        $log_path = brevo_get_log_path();
        @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] === wp_mail filter START ===\n', FILE_APPEND);

        $brevo_key = BREVO_API_KEY;
        if (empty($brevo_key)) {
            @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] BREVO_API_KEY not defined\n', FILE_APPEND);
            return $atts;
        }

        @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] API Key defined, processing email\n', FILE_APPEND);

        $to = is_array($atts['to']) ? $atts['to'][0] : $atts['to'];
        $subject = $atts['subject'];
        $message = $atts['message'];
        $headers = isset($atts['headers']) ? $atts['headers'] : '';

        @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] To: ' . $to . ' | Subject: ' . substr($subject, 0, 50) . "\n", FILE_APPEND);

        // Default sender
        $from_email = 'jarhou06@gmail.com';
        $from_name = 'Ravi';

        // Check headers for From field
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (strpos($header, 'From:') !== false) {
                    $from_email = trim(str_replace('From:', '', $header));
                }
            }
        } elseif (is_string($headers)) {
            if (strpos($headers, 'From:') !== false) {
                $from_email = trim(str_replace('From:', '', explode("\n", $headers)[0]));
            }
        }

        @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] From: ' . $from_email . "\n", FILE_APPEND);

        // Prepare Brevo API request
        $body = array(
            'sender' => array(
                'email' => $from_email,
                'name' => $from_name
            ),
            'to' => array(
                array(
                    'email' => $to
                )
            ),
            'subject' => $subject,
            'htmlContent' => $message
        );

        @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] Calling Brevo API...\n', FILE_APPEND);

        $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', array(
            'method' => 'POST',
            'headers' => array(
                'api-key' => $brevo_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] WP Error: ' . $error_msg . "\n", FILE_APPEND);
            return $atts;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] HTTP Code: ' . $http_code . "\n", FILE_APPEND);

        if ($http_code === 201) {
            @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] Email sent successfully to ' . $to . "\n", FILE_APPEND);
            $GLOBALS['brevo_mail_sent'] = true;
            return $atts;
        } else {
            $body_response = wp_remote_retrieve_body($response);
            @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] Brevo error (' . $http_code . '): ' . substr($body_response, 0, 200) . "\n", FILE_APPEND);
            return $atts;
        }
    } catch (Exception $e) {
        @file_put_contents(brevo_get_log_path(), '[' . date('Y-m-d H:i:s') . '] EXCEPTION in wp_mail filter: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
        return $atts;
    }
}, 1000);

// Make PHPMailer skip sending if Brevo already handled it
add_action('phpmailer_init', function($phpmailer) {
    try {
        if (!empty($GLOBALS['brevo_mail_sent'])) {
            $log_path = brevo_get_log_path();
            @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] Brevo mail already sent, overriding PHPMailer\n', FILE_APPEND);
            // Clear recipients to prevent actual sending
            $phpmailer->clearAllRecipients();
            // Set a dummy recipient so send() doesn't error
            $phpmailer->addBcc('noreply@localhost');
            @file_put_contents($log_path, '[' . date('Y-m-d H:i:s') . '] PHPMailer successfully overridden\n', FILE_APPEND);
        }
    } catch (Exception $e) {
        @file_put_contents(brevo_get_log_path(), '[' . date('Y-m-d H:i:s') . '] EXCEPTION in phpmailer_init: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
    }
}, 1001);
