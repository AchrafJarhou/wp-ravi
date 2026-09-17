<?php
/**
 * SendGrid Email Mailer for Production
 * Uses SendGrid API in production (Railway), Gmail SMTP in development
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if SendGrid is configured (production)
$use_sendgrid = defined('SENDGRID_API_KEY') && !empty(SENDGRID_API_KEY);

if ($use_sendgrid) {
    error_log('🚀 SendGrid mailer activated');

    // Production: Use SendGrid API
    add_filter('wp_mail', function($atts) {
        $sendgrid_key = defined('SENDGRID_API_KEY') ? SENDGRID_API_KEY : '';

        if (empty($sendgrid_key)) {
            error_log('❌ SENDGRID: API key not found');
            return $atts;
        }

        $to = is_array($atts['to']) ? $atts['to'][0] : $atts['to'];
        $subject = $atts['subject'];
        $message = $atts['message'];
        $headers = isset($atts['headers']) ? $atts['headers'] : '';

        error_log('🚀 SENDGRID: Attempting to send email to ' . $to);

        // Use verified SendGrid sender address (must be verified in SendGrid)
        $from_email = 'jarhou06@gmail.com';
        $from_name = 'Ravi';

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

        // Prepare SendGrid API request
        $body = array(
            'personalizations' => array(
                array(
                    'to' => array(
                        array(
                            'email' => $to
                        )
                    ),
                    'subject' => $subject
                )
            ),
            'from' => array(
                'email' => $from_email,
                'name' => $from_name
            ),
            'content' => array(
                array(
                    'type' => 'text/html',
                    'value' => $message
                )
            )
        );

        $response = wp_remote_post('https://api.sendgrid.com/v3/mail/send', array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $sendgrid_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            error_log('❌ SENDGRID ERROR: ' . $response->get_error_message());
            return $atts;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code === 202) {
            error_log('✅ SENDGRID: Email sent successfully to ' . $to);
            return $atts;
        } else {
            $body = wp_remote_retrieve_body($response);
            error_log('❌ SENDGRID ERROR (' . $http_code . '): ' . $body);
            return $atts;
        }
    }, 1000);
} else {
    // Development: Keep Gmail SMTP configuration from mailhog-smtp.php
    error_log('📧 Development mode: Using Gmail SMTP');
}
