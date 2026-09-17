<?php
// Configure WP Mail SMTP with Gmail SMTP using environment variables

add_action( 'plugins_loaded', function() {
    // WP Mail SMTP needs to be loaded
    if ( ! function_exists( 'wp_mail_smtp' ) ) {
        return;
    }

    // Get Gmail credentials from environment variables
    $gmail_user = getenv( 'GMAIL_USERNAME' ) ?: 'achraf.jarhou@laplateforme.io';
    $gmail_password = getenv( 'GMAIL_PASSWORD' ) ?: '';

    // Only configure if we have credentials
    if ( empty( $gmail_password ) ) {
        return;
    }

    $smtp_settings = array(
        'provider'       => 'other',
        'from_email'     => $gmail_user,
        'from_name'      => 'RAVI',
        'return_path'    => true,
        'other_smtp'     => array(
            'host'       => 'smtp.gmail.com',
            'port'       => '587',
            'encryption' => 'tls',
            'auth'       => true,
            'user'       => $gmail_user,
            'pass'       => $gmail_password,
        ),
    );

    update_option( 'wp-mail-smtp', $smtp_settings );

    // Enable logging for debugging
    update_option( 'wp-mail-smtp[log]', true );
}, 1 );
