<?php

/*=======================================
 *  Endpoint API pour le formulaire Contact
 *  Validation sécurisée + envoi d'email
 *  =============================================*/

if (!defined('ABSPATH')) {
    exit;
}

// Enregistrer l'endpoint
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/contact', array(
        'methods' => 'POST',
        'callback' => 'handle_contact_form',
        'permission_callback' => '__return_true',
    ));
});

function handle_contact_form($request) {
    // Récupérer les données
    $params = $request->get_json_params();
    $name = isset($params['name']) ? $params['name'] : '';
    $email = isset($params['email']) ? $params['email'] : '';
    $message = isset($params['message']) ? $params['message'] : '';

    // Valider et nettoyer les données
    $errors = array();

    // Valider nom
    $name = sanitize_text_field($name);
    if (empty($name) || strlen($name) < 2) {
        $errors['name'] = 'Le nom est requis (minimum 2 caractères)';
    }

    // Valider email
    $email = sanitize_email($email);
    if (empty($email) || !is_email($email)) {
        $errors['email'] = 'Un email valide est requis';
    }

    // Valider message
    $message = sanitize_textarea_field($message);
    if (empty($message) || strlen($message) < 10) {
        $errors['message'] = 'Le message est requis (minimum 10 caractères)';
    }

    // Si erreurs, retourner
    if (!empty($errors)) {
        return new WP_REST_Response(array(
            'success' => false,
            'errors' => $errors,
        ), 400);
    }

    // Vérifier rate limiting (max 50 emails par IP en dev - à réduire en prod)
    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    $cache_key = 'contact_form_' . md5($ip);
    $count = get_transient($cache_key);

    if ($count >= 50) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Trop de messages envoyés. Veuillez réessayer dans une heure.',
        ), 429);
    }

    // Incrémenter le compteur
    set_transient($cache_key, ($count ?? 0) + 1, HOUR_IN_SECONDS);

    // Récupérer l'email du magasin
    $to = get_option('admin_email');
    $subject = 'Nouveau message de contact - ' . $name;

    // Construire le contenu de l'email
    $body = "Nouveau message reçu:\n\n";
    $body .= "Nom: " . wp_kses_post($name) . "\n";
    $body .= "Email: " . wp_kses_post($email) . "\n";
    $body .= "Message:\n" . wp_kses_post($message) . "\n";

    // En-têtes
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    $headers[] = 'From: ' . wp_kses_post($name) . ' <' . $email . '>';

    error_log('Contact form received: ' . $name . ' (' . $email . ')');
    error_log('Message: ' . $message);

    wp_mail($to, $subject, $body, $headers);
    wp_mail($email, 'Merci pour votre message', "Bonjour,\n\nMerci pour votre message.\n\nCordialement,\nL'équipe");

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Message envoyé avec succès!',
    ), 200);
}
