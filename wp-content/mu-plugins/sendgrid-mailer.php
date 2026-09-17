<?php
/**
 * Brevo Email Mailer for Production
 * Uses Brevo API in production (Railway), Gmail SMTP in development
 * No DNS required - simple API-based solution
 */

if (!defined('ABSPATH')) {
    exit;
}

// Log to stderr
fwrite(STDERR, "[BREVO] Plugin loaded\n");

// Check if Brevo is configured (production)
$use_brevo = defined('BREVO_API_KEY') && !empty(BREVO_API_KEY);
fwrite(STDERR, "[BREVO] BREVO_API_KEY defined: " . (defined('BREVO_API_KEY') ? 'YES' : 'NO') . "\n");
fwrite(STDERR, "[BREVO] BREVO_API_KEY value: " . (empty(BREVO_API_KEY) ? 'EMPTY' : 'HAS_VALUE') . "\n");
fwrite(STDERR, "[BREVO] use_brevo flag: " . ($use_brevo ? 'TRUE' : 'FALSE') . "\n");

if ($use_brevo) {
    error_log('🚀 Brevo mailer activated');

    // Production: Use Brevo API
    add_filter('wp_mail', function($atts) {
        $brevo_key = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';

        if (empty($brevo_key)) {
            fwrite(STDERR, "[BREVO] ❌ API key not found\n");
            return $atts;
        }

        $to = is_array($atts['to']) ? $atts['to'][0] : $atts['to'];
        $subject = $atts['subject'];
        $message = $atts['message'];
        $headers = isset($atts['headers']) ? $atts['headers'] : '';

        fwrite(STDERR, "[BREVO] 🚀 Attempting to send email to " . $to . "\n");

        // Use verified Brevo sender address
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
            fwrite(STDERR, "[BREVO] ❌ ERROR: " . $response->get_error_message() . "\n");
            return $atts;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code === 201) {
            fwrite(STDERR, "[BREVO] ✅ Email sent successfully to " . $to . "\n");
            return $atts;
        } else {
            $body = wp_remote_retrieve_body($response);
            fwrite(STDERR, "[BREVO] ❌ ERROR (" . $http_code . "): " . $body . "\n");
            return $atts;
        }
    }, 1000);
} else {
    // Development: Keep Gmail SMTP configuration from mailhog-smtp.php
    fwrite(STDERR, "[EMAIL] Development mode: Using Gmail SMTP\n");
}
