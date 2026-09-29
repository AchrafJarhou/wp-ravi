<?php

/*=======================================
 *  wp-json/wc/store/v1/products renvoie, pour les produits variables, une cle
 *  "variations" qui ne contient que id/attributes/permalink : ni is_in_stock
 *  ni stock_status. Le front a besoin de ces infos par variation (ex: griser
 *  un choix de taille/couleur en rupture) sans faire un appel par variation.
 *  On reexpose donc le Store API via une route custom qui enrichit chaque
 *  entree de "variations" avec ces deux champs.
 *  =============================================*/

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/products', [
        'methods'             => 'GET',
        'callback'            => 'headless_get_products_with_variation_stock',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('custom/v1', '/products/(?P<id>[\w-]+)', [
        'methods'             => 'GET',
        'callback'            => 'headless_get_single_product_with_variation_stock',
        'permission_callback' => '__return_true',
    ]);
});

function headless_enrich_product_images($product_data)
{
    $product_id = is_object($product_data) ? ($product_data->id ?? null) : ($product_data['id'] ?? null);

    if (!$product_id) {
        return $product_data;
    }

    $product = wc_get_product($product_id);

    if (!$product) {
        return $product_data;
    }

    $images = [];

    // Image principale
    $image_id = $product->get_image_id();
    if ($image_id) {
        $image_url = wp_get_attachment_image_url($image_id, 'full');
        if ($image_url) {
            $images[] = ['id' => $image_id, 'src' => $image_url];
        }
    }

    // Images de galerie
    $gallery_ids = $product->get_gallery_image_ids();
    if ($gallery_ids && is_array($gallery_ids)) {
        foreach ($gallery_ids as $gal_id) {
            $image_url = wp_get_attachment_image_url($gal_id, 'full');
            if ($image_url) {
                $images[] = ['id' => $gal_id, 'src' => $image_url];
            }
        }
    }

    if (is_object($product_data)) {
        $product_data->images = $images;
    } else {
        $product_data['images'] = $images;
    }

    return $product_data;
}

/*
 * Le Store API renvoie les termes d'un attribut par ordre alphabetique
 * (3XL, L, M, S...) et ignore l'ordre regle dans Produits > Attributs.
 * wc_get_product_terms respecte ce reglage : on s'en sert pour retrier.
 */
function headless_sort_attribute_terms($product_data)
{
    $product_id = is_object($product_data) ? ($product_data->id ?? null) : ($product_data['id'] ?? null);
    $attributes = is_object($product_data) ? ($product_data->attributes ?? null) : ($product_data['attributes'] ?? null);

    if (!$product_id || empty($attributes) || !is_array($attributes)) {
        return $product_data;
    }

    $sorted = array_map(function ($attribute) use ($product_id) {
        $taxonomy = is_object($attribute) ? ($attribute->taxonomy ?? null) : ($attribute['taxonomy'] ?? null);
        $terms    = is_object($attribute) ? ($attribute->terms ?? null) : ($attribute['terms'] ?? null);

        if (!$taxonomy || empty($terms) || !is_array($terms)) {
            return $attribute;
        }

        $ordered_ids = wc_get_product_terms($product_id, $taxonomy, ['fields' => 'ids']);
        $position    = array_flip(array_map('intval', $ordered_ids));

        usort($terms, function ($a, $b) use ($position) {
            $id_a = (int) (is_object($a) ? ($a->id ?? 0) : ($a['id'] ?? 0));
            $id_b = (int) (is_object($b) ? ($b->id ?? 0) : ($b['id'] ?? 0));

            return ($position[$id_a] ?? PHP_INT_MAX) <=> ($position[$id_b] ?? PHP_INT_MAX);
        });

        if (is_object($attribute)) {
            $attribute->terms = $terms;
        } else {
            $attribute['terms'] = $terms;
        }

        return $attribute;
    }, $attributes);

    if (is_object($product_data)) {
        $product_data->attributes = $sorted;
    } else {
        $product_data['attributes'] = $sorted;
    }

    return $product_data;
}

/*
 * Le Store API ne donne qu'un prix par produit variable (le plus bas, plus
 * une fourchette) : sans prix par variation, le front ne peut pas afficher
 * celui de la taille choisie. On le fournit au meme format que
 * product.prices (chaines en plus petite unite, "3499" pour 34,99 EUR),
 * taxes affichees selon le reglage de la boutique.
 */
function headless_variation_prices($variation_product)
{
    $to_minor_unit = function ($amount) {
        return (string) (int) round((float) $amount * pow(10, wc_get_price_decimals()));
    };

    $price   = wc_get_price_to_display($variation_product);
    $regular = wc_get_price_to_display($variation_product, ['price' => $variation_product->get_regular_price()]);

    return [
        'price'         => $to_minor_unit($price),
        'regular_price' => $to_minor_unit($regular),
        'sale_price'    => $to_minor_unit($price),
    ];
}

function headless_enrich_variation_stock($product_data)
{
    $product_data = headless_enrich_product_images($product_data);
    $product_data = headless_sort_attribute_terms($product_data);

    // Selon le contexte, le Store API renvoie les entrees de "variations" en
    // stdClass ou en tableau associatif : on gere les deux formes.
    $variations = is_object($product_data) ? ($product_data->variations ?? null) : ($product_data['variations'] ?? null);

    if (empty($variations) || !is_array($variations)) {
        return $product_data;
    }

    $enriched = array_map(function ($variation) {
        $variation_id      = is_object($variation) ? ($variation->id ?? null) : ($variation['id'] ?? null);
        $variation_product = $variation_id ? wc_get_product($variation_id) : null;

        $is_in_stock  = $variation_product ? $variation_product->is_in_stock() : null;
        $stock_status = $variation_product ? $variation_product->get_stock_status() : null;

        // Meme logique que le low_stock_remaining du Store API pour un produit
        // simple : la quantite n'est exposee que sous le seuil de stock faible
        // (WooCommerce > Reglages > Produits > Inventaire), sinon null.
        $low_stock_remaining = null;
        if ($variation_product && $variation_product->managing_stock()) {
            $quantity = (int) $variation_product->get_stock_quantity();
            if ($quantity > 0 && $quantity <= wc_get_low_stock_amount($variation_product)) {
                $low_stock_remaining = $quantity;
            }
        }

        $prices = $variation_product ? headless_variation_prices($variation_product) : null;

        if (is_object($variation)) {
            $variation->is_in_stock         = $is_in_stock;
            $variation->stock_status        = $stock_status;
            $variation->low_stock_remaining = $low_stock_remaining;
            $variation->prices              = $prices;
        } else {
            $variation['is_in_stock']         = $is_in_stock;
            $variation['stock_status']        = $stock_status;
            $variation['low_stock_remaining'] = $low_stock_remaining;
            $variation['prices']              = $prices;
        }

        return $variation;
    }, $variations);

    if (is_object($product_data)) {
        $product_data->variations = $enriched;
    } else {
        $product_data['variations'] = $enriched;
    }

    return $product_data;
}

function headless_get_products_with_variation_stock($request)
{
    if (!function_exists('wc_get_product')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce est requis pour cette fonctionnalite.', ['status' => 500]);
    }

    // On relaie tel quel le Store API pour garder son filtrage/tri/pagination,
    // on ne fait qu'enrichir la reponse ensuite.
    $store_request = new WP_REST_Request('GET', '/wc/store/v1/products');
    $store_request->set_query_params($request->get_query_params());

    $store_response = rest_do_request($store_request);

    if ($store_response->is_error()) {
        $error = $store_response->as_error();
        return $error instanceof WP_Error
            ? $error
            : new WP_Error('products_fetch_failed', 'Impossible de recuperer les produits.', ['status' => 500]);
    }

    $products = array_map('headless_enrich_variation_stock', $store_response->get_data());

    $response = rest_ensure_response($products);

    // On repercute les headers de pagination du Store API, utilises par le front
    $store_headers = $store_response->get_headers();
    foreach (['X-WP-Total', 'X-WP-TotalPages'] as $header) {
        if (isset($store_headers[$header])) {
            $response->header($header, $store_headers[$header]);
        }
    }

    return $response;
}

function headless_resolve_product_id($raw_id)
{
    if (is_numeric($raw_id)) {
        return (int) $raw_id;
    }

    $query = new WP_Query([
        'name'           => sanitize_title($raw_id),
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    return $query->have_posts() ? (int) $query->posts[0] : 0;
}

function headless_get_single_product_with_variation_stock($request)
{
    if (!function_exists('wc_get_product')) {
        return new WP_Error('woocommerce_unavailable', 'WooCommerce est requis pour cette fonctionnalite.', ['status' => 500]);
    }

    $product_id = headless_resolve_product_id($request->get_param('id'));

    if (!$product_id) {
        return new WP_Error('product_not_found', 'Produit introuvable.', ['status' => 404]);
    }

    $store_request  = new WP_REST_Request('GET', '/wc/store/v1/products/' . $product_id);
    $store_response = rest_do_request($store_request);

    if ($store_response->is_error()) {
        $error = $store_response->as_error();
        return $error instanceof WP_Error
            ? $error
            : new WP_Error('product_fetch_failed', 'Impossible de recuperer le produit.', ['status' => 500]);
    }

    return rest_ensure_response(headless_enrich_variation_stock($store_response->get_data()));
}
