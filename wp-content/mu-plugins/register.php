<?php

/*=======================================
 *  Ce code a été ajouté par Amad pour tester l'inscription
 *  =============================================*/

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/register', [
        'methods'             => 'POST',
        'callback'            => 'headless_register_user',
        'permission_callback' => '__return_true',
    ]);
});

function headless_register_rate_limit_check()
{
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $key = 'headless_register_' . md5($ip);

    $attempts = (int) get_transient($key);

    if ($attempts >= 20) {
        return false;
    }

    set_transient($key, $attempts + 1, HOUR_IN_SECONDS);
    return true;
}

function headless_register_user($request)
{
    if (!headless_register_rate_limit_check()) {
        return new WP_Error('too_many_requests', 'Trop de tentatives. Reessayez dans une heure.', ['status' => 429]);
    }

    $email     = sanitize_email($request->get_param('email'));
    $password  = (string) $request->get_param('password');
    $firstName = sanitize_text_field($request->get_param('firstName'));
    $lastName  = sanitize_text_field($request->get_param('lastName'));

    if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        return new WP_Error('missing_fields', 'Email, mot de passe, prénom et nom sont requis.', ['status' => 400]);
    }
    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'Adresse email invalide.', ['status' => 400]);
    }
    if (strlen($password) < 8) {
        return new WP_Error('weak_password', 'Le mot de passe doit contenir au moins 8 caracteres.', ['status' => 400]);
    }
    if (email_exists($email)) {
        return new WP_Error('email_exists', 'Un compte existe deja avec cet email.', ['status' => 409]);
    }

    $base_username = sanitize_user(strtolower($firstName));
    $username      = $base_username;
    $counter       = 1;

    while (username_exists($username)) {
        $username = $base_username . $counter;
        $counter++;
    }

    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        return new WP_Error('registration_failed', $user_id->get_error_message(), ['status' => 500]);
    }

    update_user_meta($user_id, 'first_name', $firstName);
    update_user_meta($user_id, 'last_name', $lastName);

    headless_send_welcome_email($firstName, $email);

    $token_request = new WP_REST_Request('POST', '/jwt-auth/v1/token');
    $token_request->set_param('username', $username);
    $token_request->set_param('password', $password);
    $token_response = rest_do_request($token_request);

    if ($token_response->is_error()) {
        return new WP_Error('token_generation_failed', 'Compte cree, mais la connexion automatique a echoue.', ['status' => 500]);
    }

    return rest_ensure_response($token_response->get_data());
}

function headless_send_welcome_email($firstName, $email)
{
    $subject = 'Bienvenue !';
    $body = "Bonjour " . sanitize_text_field($firstName) . ",\n\n";
    $body .= "Merci de vous être inscrit sur notre boutique.\n\n";
    $body .= "Vous pouvez maintenant commencer à faire vos achats.\n\n";
    $body .= "Si vous avez des questions, n'hésitez pas à nous contacter.\n\n";
    $body .= "Cordialement,\nL'équipe";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    wp_mail($email, $subject, $body, $headers);
}
