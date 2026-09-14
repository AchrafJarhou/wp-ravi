<?php

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/reset-password', [
        'methods'             => 'POST',
        'callback'            => 'headless_reset_password',
        'permission_callback' => '__return_true',
    ]);
});

function headless_reset_password($request)
{
    $email = sanitize_email($request->get_param('email'));

    if (empty($email)) {
        return new WP_Error('missing_email', 'Email requis.', ['status' => 400]);
    }

    $user = get_user_by('email', $email);

    if (!$user) {
        return new WP_Error('user_not_found', 'Aucun compte trouvé avec cet email.', ['status' => 404]);
    }

    $key = get_password_reset_key($user);

    if (is_wp_error($key)) {
        return new WP_Error('key_error', 'Erreur lors de la génération du lien.', ['status' => 500]);
    }

    $reset_link = "http://localhost:5173/new-password?login=" . rawurlencode($user->user_login) . "&key=" . rawurlencode($key);


    wp_mail(
        $email,
        'Réinitialisation de votre mot de passe',
        "Cliquez sur ce lien pour réinitialiser votre mot de passe :\n\n{$reset_link}"
    );

    return rest_ensure_response(['message' => 'Email de réinitialisation envoyé.']);
}

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/new-password', [
        'methods'             => 'POST',
        'callback'            => 'headless_new_password',
        'permission_callback' => '__return_true',
    ]);
});

function headless_new_password($request)
{
    $key      = $request->get_param('key');
    $login    = $request->get_param('login');
    $password = $request->get_param('password');

    if (empty($key) || empty($login) || empty($password)) {
        return new WP_Error('missing_fields', 'Tous les champs sont requis.', ['status' => 400]);
    }

    $user = check_password_reset_key($key, $login);

    if (is_wp_error($user)) {
        return new WP_Error('invalid_key', 'Lien invalide ou expiré.', ['status' => 400]);
    }

    reset_password($user, $password);

    return rest_ensure_response(['message' => 'Mot de passe modifié avec succès.']);
}