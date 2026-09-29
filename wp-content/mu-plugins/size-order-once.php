<?php

/*=======================================
 *  TEMPORAIRE — a supprimer une fois l'ordre applique.
 *  Applique l'ordre des termes des attributs Taille et Pointure sans passer
 *  par le glisser-deposer de l'admin (pagination, pas d'options d'ecran).
 *  S'execute a l'ouverture de l'admin par un gestionnaire de la boutique,
 *  jusqu'a ce que tous les termes attendus existent, puis ne fait plus rien.
 *  Ne touche qu'a l'ordre des termes, ni aux produits ni au stock.
 *  =============================================*/

add_action('admin_init', 'headless_apply_size_order_once');

function headless_size_order_lists()
{
    return [
        'pa_taille' => [
            'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL',
            '32', '34', '36', '38', '40', '42', '44', '46', '48', '50',
            'W26', 'W27', 'W28', 'W29', 'W30', 'W31', 'W32', 'W33', 'W34', 'W35', 'W36', 'W38', 'W40', 'W42', 'W44',
            'TU',
        ],
        'pa_pointure' => [
            '35', '35.5', '36', '36.5', '37', '37.5', '38', '38.5', '39', '39.5', '40', '40.5',
            '41', '41.5', '42', '42.5', '43', '43.5', '44', '44.5', '45', '45.5', '46', '46.5', '47',
        ],
    ];
}

function headless_apply_size_order_once()
{
    if (get_option('headless_size_order_done') || !current_user_can('manage_woocommerce')) {
        return;
    }

    $report   = [];
    $complete = true;

    foreach (headless_size_order_lists() as $taxonomy => $names) {
        if (!taxonomy_exists($taxonomy)) {
            $report[] = $taxonomy . ' : attribut introuvable';
            $complete = false;
            continue;
        }

        $found = 0;
        foreach ($names as $position => $name) {
            $term = get_term_by('name', $name, $taxonomy);
            if ($term) {
                update_term_meta($term->term_id, 'order', $position + 1);
                $found++;
            }
        }

        // Les termes hors liste (ajoutes plus tard par le client) passent apres.
        $next  = count($names) + 1;
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        foreach ($terms as $term) {
            if (!in_array($term->name, $names, true)) {
                update_term_meta($term->term_id, 'order', $next++);
            }
        }

        $report[] = sprintf('%s : %d/%d termes ordonnes', $taxonomy, $found, count($names));
        if ($found < count($names)) {
            $complete = false;
        }
    }

    if ($complete) {
        update_option('headless_size_order_done', 1, false);
    }

    add_action('admin_notices', function () use ($report, $complete) {
        printf(
            '<div class="notice notice-%s"><p><strong>Ordre des tailles :</strong> %s%s</p></div>',
            $complete ? 'success' : 'warning',
            esc_html(implode(' — ', $report)),
            $complete ? '. Termine, le fichier size-order-once.php peut etre supprime.' : '. Des termes manquent : ils seront ordonnes a la prochaine ouverture de l\'admin une fois crees.'
        );
    });
}
