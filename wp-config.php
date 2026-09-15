<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - Auto-detect local vs production ** //
if ( getenv( 'DB_HOST' ) ) {
    // Production (Railway)
    define( 'DB_NAME', getenv( 'DB_NAME' ) );
    define( 'DB_USER', getenv( 'DB_USER' ) );
    define( 'DB_PASSWORD', getenv( 'DB_PASSWORD' ) );
    define( 'DB_HOST', getenv( 'DB_HOST' ) );
} else {
    // Local development
    define( 'DB_NAME', 'ravie_woocomerce' );
    define( 'DB_USER', 'root' );
    define( 'DB_PASSWORD', '' );
    define( 'DB_HOST', 'localhost' );
}

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', 'utf8mb4_unicode_ci' );


define('JWT_AUTH_SECRET_KEY', '$fE$lxFhGZ&$35;&3kHp:%XW-rg4syrbsmE.-!){>eQ*KA4Xu}$.3BAgnIna3Os.');
define('JWT_AUTH_CORS_ENABLE', true);

// Gmail SMTP Credentials
define('GMAIL_USERNAME', 'achraf.jarhou@laplateforme.io');
define('GMAIL_PASSWORD', 'ydbsvsahgpduysfz');

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

/* Add any custom values between this line and the "stop editing" line. */

// Accepte localhost ET l'IP du réseau
if ($_SERVER['HTTP_HOST'] === '10.60.4.51' || $_SERVER['HTTP_HOST'] === 'localhost') {
    define('WP_HOME', 'http://' . $_SERVER['HTTP_HOST'] . '/wordpress-ravi');
    define('WP_SITEURL', 'http://' . $_SERVER['HTTP_HOST'] . '/wordpress-ravi');
}

/* That's all, stop editing! Happy publishing. */

// CORS Headers - send before WordPress starts
$allowed_origins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://10.60.4.51:5173',
    'https://template-woo-commerce-headless.vercel.app',
    'https://template-woo-commerce-headless-3jh7wv8rz.vercel.app',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cart-Token, Nonce');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Expose-Headers: Content-Type, X-WP-Nonce');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit();
    }
}

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

