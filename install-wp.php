<?php
// Automatic WordPress installation script
define('WP_INSTALLING', true);

require_once __DIR__ . '/wp-load.php';

// Check if already installed
if (wp_cache_get('alloptions') !== false) {
    echo "WordPress already installed!";
    exit;
}

// Setup database
global $wpdb, $wp_version;

// Create tables
require_once __DIR__ . '/wp-admin/includes/upgrade.php';
wp_install_defaults(1);

// Create admin user
wp_create_user('admin', 'AdminRavie123!', 'admin@ravie.local');
$admin = get_user_by('login', 'admin');
$admin->set_role('administrator');

// Update site options
update_option('blogname', 'Ravie');
update_option('blogdescription', 'Ravie WooCommerce');
update_option('admin_email', 'admin@ravie.local');

echo "WordPress installation complete!";
?>
