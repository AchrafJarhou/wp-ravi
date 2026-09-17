<?php
// Configure WP Mail SMTP with Gmail SMTP

// Only configure if plugin is activated
add_action( 'plugins_loaded', function() {
    if ( ! function_exists( 'wp_mail_smtp' ) ) {
        return;
    }

    $smtp_settings = array(
        'provider'       => 'other',
        'from_email'     => 'achraf.jarhou@laplateforme.io',
        'from_name'      => 'RAVI',
        'return_path'    => true,
        'other_smtp'     => array(
            'host'       => 'smtp.gmail.com',
            'port'       => '587',
            'encryption' => 'tls',
            'auth'       => true,
            'user'       => 'achraf.jarhou@laplateforme.io',
            'pass'       => 'ydbsvsahgpduysfz',
        ),
    );

    update_option( 'wp-mail-smtp', $smtp_settings );
}, 1 );
