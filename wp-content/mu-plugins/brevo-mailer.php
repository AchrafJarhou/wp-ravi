<?php
/**
 * Brevo Email Mailer for Production
 * Uses Brevo API - simple, no DNS required
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only activate if BREVO_API_KEY is configured
if (!defined('BREVO_API_KEY') || empty(BREVO_API_KEY)) {
    // Development mode - Gmail SMTP handled by mailhog-smtp.php
    return;
}

// Production: Use Brevo API
add_filter('wp_mail', function($atts) {
    $brevo_key = BREVO_API_KEY;

    if (empty($brevo_key)) {
        return $atts;
    }

    $to = is_array($atts['to']) ? $atts['to'][0] : $atts['to'];
    $subject = $atts['subject'];
    $message = $atts['message'];
    $headers = isset($atts['headers']) ? $atts['headers'] : '';

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
        error_log('[BREVO] WP Error: ' . $response->get_error_message());
        return $atts;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code === 201) {
        error_log('[BREVO] Email sent to ' . $to);
        return $atts;
    } else {
        $body_response = wp_remote_retrieve_body($response);
        error_log('[BREVO] Error (' . $http_code . '): ' . $body_response);
        return $atts;
    }
}, 1000);
