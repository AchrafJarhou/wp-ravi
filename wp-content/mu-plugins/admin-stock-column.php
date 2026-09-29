<?php

/*=======================================
 *  Colonne « Stock » de Produits > Tous les produits
 *  Pour un produit variable, WooCommerce n'affiche que « En stock » : il
 *  faudrait ouvrir chaque produit pour connaitre le stock de chaque taille.
 *  On ajoute sous ce statut la quantite restante par taille, dans l'ordre
 *  regle dans Produits > Attributs (rupture en rouge, stock faible en orange).
 *  =============================================*/

add_filter('woocommerce_admin_stock_html', 'headless_admin_variation_stock_html', 10, 2);

function headless_admin_variation_stock_html($stock_html, $product)
{
    if (!$product || !$product->is_type('variable')) {
        return $stock_html;
    }

    $variations = array_filter(array_map('wc_get_product', $product->get_children()));

    if (empty($variations)) {
        return $stock_html;
    }

    usort($variations, function ($a, $b) use ($product) {
        return headless_admin_variation_position($product, $a) <=> headless_admin_variation_position($product, $b);
    });

    $labels = array_map('headless_admin_variation_stock_label', $variations);

    return $stock_html . '<br><small>' . implode(' · ', $labels) . '</small>';
}

// Position de la taille de la variation dans l'ordre defini pour l'attribut.
// Un attribut personnalise (saisi dans la fiche produit) n'a pas d'ordre : la
// variation garde alors sa place d'origine, en fin de liste.
function headless_admin_variation_position($product, $variation)
{
    static $positions = [];

    foreach ($variation->get_attributes() as $taxonomy => $slug) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        $key = $product->get_id() . '|' . $taxonomy;
        if (!isset($positions[$key])) {
            $slugs = wc_get_product_terms($product->get_id(), $taxonomy, ['fields' => 'slugs']);
            $positions[$key] = array_flip($slugs);
        }

        return $positions[$key][$slug] ?? PHP_INT_MAX;
    }

    return PHP_INT_MAX;
}

function headless_admin_variation_stock_label($variation)
{
    $name = wc_get_formatted_variation($variation, true, false, false);

    if ($variation->managing_stock()) {
        $quantity = (int) $variation->get_stock_quantity();
        $text     = $name . ' : ' . $quantity;

        if ($quantity <= 0) {
            $color = '#d63638';
        } elseif ($quantity <= wc_get_low_stock_amount($variation)) {
            $color = '#dba617';
        } else {
            $color = null;
        }
    } else {
        // Variation sans gestion de stock : seul son statut est connu.
        $in_stock = $variation->is_in_stock();
        $text     = $name . ' : ' . ($in_stock ? 'en stock' : 'rupture');
        $color    = $in_stock ? null : '#d63638';
    }

    $text = esc_html($text);

    return $color
        ? '<span style="color:' . $color . ';font-weight:600">' . $text . '</span>'
        : $text;
}
