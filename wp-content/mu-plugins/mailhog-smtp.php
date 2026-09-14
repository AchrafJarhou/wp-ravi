<?php
/**
 * Email Configuration for Gmail SMTP
 * Using PHPMailer with Gmail App Password for development
 */

if (!defined('ABSPATH')) {
    exit;
}

// Hook into PHPMailer and configure Gmail SMTP
add_action('phpmailer_init', function($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.gmail.com';
    $phpmailer->Port = 587;
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->SMTPAuth = true;

    $phpmailer->Username = defined('GMAIL_USERNAME') ? GMAIL_USERNAME : '';
    $phpmailer->Password = defined('GMAIL_PASSWORD') ? GMAIL_PASSWORD : '';

    $phpmailer->SMTPKeepAlive = true;
    $phpmailer->Timeout = 10;

    error_log('📧 Envoi via Gmail SMTP: ' . $phpmailer->Username);
});

// Set email format to HTML
add_filter('wp_mail_content_type', function() {
    return 'text/html; charset=UTF-8';
});

// Log emails being sent
add_filter('wp_mail', function($atts) {
    $to = is_array($atts['to']) ? implode(', ', $atts['to']) : $atts['to'];
    error_log('📧 EMAIL: To=' . $to . ' | Subject=' . $atts['subject']);
    return $atts;
});

// Set from email
add_filter('wp_mail_from', function($from) {
    return 'achraf.jarhou@laplateforme.io';
});

add_filter('wp_mail_from_name', function($name) {
    return 'Ravi';
});
