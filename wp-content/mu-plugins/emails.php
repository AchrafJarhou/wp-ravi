<?php

/*=======================================
 *  E-mails WooCommerce en headless
 *  - Le lien « Mon compte » des e-mails pointe vers le profil du front React
 *    au lieu de la page Mon compte de WordPress, que personne n'utilise.
 *  - Un template d'e-mail se personnalise en le copiant dans
 *    mu-plugins/woocommerce/, avec la meme arborescence que le dossier
 *    templates/ du plugin (ex : woocommerce/emails/customer-new-account.php).
 *    Contrairement au dossier du theme, cette copie est versionnee avec le
 *    reste du back et ne disparait pas a la mise a jour du theme.
 *  =============================================*/

// URL du front React. Sur un autre serveur, la redefinir dans wp-config.php :
// define('HEADLESS_FRONT_URL', 'https://...');
if (!defined('HEADLESS_FRONT_URL')) {
    define('HEADLESS_FRONT_URL', 'http://localhost:5173');
}

function headless_front_profile_url()
{
    return untrailingslashit(HEADLESS_FRONT_URL) . '/profile';
}

// Uniquement le temps de generer le corps d'un e-mail : ailleurs, la page
// Mon compte sert encore a WordPress (lien « Mot de passe oublie » de la page
// de connexion de l'admin, par exemple).
add_action('woocommerce_email_header', function () {
    add_filter('woocommerce_get_myaccount_page_permalink', 'headless_front_profile_url');
});

add_action('woocommerce_email_footer', function () {
    remove_filter('woocommerce_get_myaccount_page_permalink', 'headless_front_profile_url');
});

// Seuls les fichiers a la racine de mu-plugins sont executes par WordPress :
// les templates ranges dans le sous-dossier woocommerce/ ne sont charges que
// par ce filtre.
add_filter('woocommerce_locate_template', function ($template, $template_name) {
    $override = __DIR__ . '/woocommerce/' . $template_name;

    return file_exists($override) ? $override : $template;
}, 10, 2);
