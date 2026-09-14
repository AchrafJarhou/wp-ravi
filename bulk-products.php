<?php
/**
 * Script pour ajouter rapidement plusieurs produits WooCommerce avec :
 * - Catégories Homme/Femme
 * - Tailles (XS, S, M, L, XL, XXL)
 * - Images depuis Unsplash
 *
 * Accès : http://localhost/wordpress-ravi/bulk-products.php?action=create&count=20
 */

require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_woocommerce')) {
    die('Accès refusé. Vous devez être connecté en tant qu\'administrateur.');
}

$action = sanitize_text_field($_GET['action'] ?? '');
$count = intval($_GET['count'] ?? 10);

if ($action === 'create') {
    create_bulk_products($count);
} elseif ($action === 'delete') {
    delete_all_products();
} elseif ($action === 'setup') {
    setup_categories_and_attributes();
} else {
    show_menu();
}

function setup_categories_and_attributes() {
    // Créer les catégories
    $categories = ['Homme', 'Femme'];
    $cat_ids = [];

    foreach ($categories as $cat_name) {
        $existing = term_exists($cat_name, 'product_cat');
        if ($existing) {
            $cat_ids[$cat_name] = $existing['term_id'];
        } else {
            $result = wp_insert_term($cat_name, 'product_cat', ['description' => 'Produits ' . $cat_name]);
            if (!is_wp_error($result)) {
                $cat_ids[$cat_name] = $result['term_id'];
            }
        }
    }

    // Créer l'attribut Taille
    $attribute_name = 'taille';
    $attribute_label = 'Taille';
    $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_name);

    if (!$attribute_id) {
        $attribute_id = wc_create_attribute([
            'name' => $attribute_label,
            'slug' => $attribute_name,
            'type' => 'select',
            'orderby' => 'menu_order',
            'has_archives' => false,
        ]);
    }

    // Ajouter les valeurs de tailles
    $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    $attribute_taxonomy = wc_attribute_taxonomy_name($attribute_name);

    foreach ($sizes as $size) {
        $existing = term_exists($size, $attribute_taxonomy);
        if (!$existing) {
            wp_insert_term($size, $attribute_taxonomy);
        }
    }

    echo "<div style='margin: 20px; font-family: Arial;'>";
    echo "<h2 style='color: green;'>✓ Configuration terminée !</h2>";
    echo "<p><strong>Catégories créées :</strong> Homme, Femme</p>";
    echo "<p><strong>Attribut créé :</strong> Taille (XS, S, M, L, XL, XXL)</p>";
    echo "<p><a href='?'>← Retour</a></p>";
    echo "</div>";
}

function get_unsplash_image($keyword) {
    // Utilise les images Unsplash (sans clé API)
    $keywords = ['fashion', 'clothing', 'shirt', 'dress', 'jacket', 'pants'];
    $random_keyword = $keywords[array_rand($keywords)];

    return 'https://images.unsplash.com/photo-' . mt_rand(1500000000, 1600000000) . '?w=500&h=500&fit=crop&crop=entropy';
}

function download_and_attach_image($image_url, $product_id) {
    // Génère un nom unique
    $filename = 'product-' . $product_id . '-' . time() . '.jpg';

    // Télécharge l'image
    $response = wp_remote_get($image_url, ['timeout' => 30]);

    if (is_wp_error($response)) {
        return 0;
    }

    $image_data = wp_remote_retrieve_body($response);

    if (empty($image_data)) {
        return 0;
    }

    // Upload vers MediaLibrary
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;

    if (file_put_contents($file_path, $image_data) === false) {
        return 0;
    }

    // Enregistre comme attachment
    $attachment = [
        'post_mime_type' => 'image/jpeg',
        'post_title'     => 'Produit ' . $product_id,
        'post_parent'    => $product_id,
        'guid'           => $upload_dir['url'] . '/' . $filename,
    ];

    $attachment_id = wp_insert_attachment($attachment, $file_path, $product_id);

    if (is_wp_error($attachment_id)) {
        return 0;
    }

    // Génère les thumbnails
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_path));

    return $attachment_id;
}

function create_bulk_products($count) {
    $start_time = time();
    $created = 0;
    $genders = ['Homme', 'Femme'];
    $adjectives = ['Classique', 'Premium', 'Confortable', 'Élégant', 'Sportif', 'Tendance', 'Casual'];
    $items = ['T-shirt', 'Chemise', 'Pantalon', 'Robe', 'Veste', 'Jeans', 'Sweat'];
    $attribute_taxonomy = wc_attribute_taxonomy_name('taille');
    $sizes = get_terms(['taxonomy' => $attribute_taxonomy, 'fields' => 'ids']);

    // Récupérer les IDs de catégories
    $cat_homme = get_term_by('name', 'Homme', 'product_cat');
    $cat_femme = get_term_by('name', 'Femme', 'product_cat');
    $cat_ids = [];
    if ($cat_homme) $cat_ids[] = $cat_homme->term_id;
    if ($cat_femme) $cat_ids[] = $cat_femme->term_id;

    if (empty($cat_ids)) {
        echo "<div style='color: red; margin: 20px;'>";
        echo "<h2>❌ Erreur !</h2>";
        echo "<p>Les catégories n'existent pas. <a href='?action=setup'>Cliquez ici pour les créer</a></p>";
        echo "</div>";
        return;
    }

    for ($i = 1; $i <= $count; $i++) {
        $gender = $genders[mt_rand(0, 1)];
        $gender_cat = ($gender === 'Homme') ? $cat_homme->term_id : $cat_femme->term_id;

        $adjective = $adjectives[array_rand($adjectives)];
        $item = $items[array_rand($items)];
        $name = $adjective . ' ' . $item . ' ' . $gender . ' #' . $i;
        $price = mt_rand(1500, 15000) / 100; // Entre 15€ et 150€
        $stock = mt_rand(5, 100);

        // Créer le produit variable (pour les tailles)
        $product = new WC_Product_Variable();
        $product->set_name($name);
        $product->set_short_description('Description courte du ' . $name . '. Produit de test avec tailles disponibles.');
        $product->set_status('publish');
        $product->set_category_ids([$gender_cat]);

        // Télécharger et attacher une image
        $image_url = get_unsplash_image($item);
        $image_id = download_and_attach_image($image_url, 0);
        if ($image_id > 0) {
            $product->set_image_id($image_id);
        }

        // Définir l'attribut de taille disponible
        $attributes = [];
        if (!empty($sizes)) {
            $attributes['taille'] = new WC_Product_Attribute();
            $attributes['taille']->set_id(wc_attribute_id_by_name('taille'));
            $attributes['taille']->set_name('taille');
            $attributes['taille']->set_options($sizes);
            $attributes['taille']->set_visible(true);
            $attributes['taille']->set_variation(true);
        }
        $product->set_attributes($attributes);

        $product_id = $product->save();

        if ($product_id) {
            // Créer les variations pour chaque taille
            if (!empty($sizes)) {
                create_product_variations($product_id, $sizes, $price, $stock);
            } else {
                // Fallback : créer comme produit simple
                $product = new WC_Product_Simple($product_id);
                $product->set_price($price);
                $product->set_stock_quantity($stock);
                $product->set_manage_stock(true);
                $product->set_stock_status('instock');
                $product->save();
            }

            $created++;
            if ($i % 5 === 0) {
                echo "✓ $i/$count produits créés...<br>";
                flush();
            }
        }
    }

    $elapsed = time() - $start_time;
    echo "<div style='margin: 20px; font-family: Arial;'>";
    echo "<h2 style='color: green;'>✓ Succès !</h2>";
    echo "<p><strong>$created produits créés</strong> en $elapsed secondes</p>";
    echo "<p><a href='?'>← Retour</a></p>";
    echo "</div>";
}

function create_product_variations($product_id, $size_ids, $price, $stock) {
    foreach ($size_ids as $size_id) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product_id);
        $variation->set_attributes(['taille' => $size_id]);
        $variation->set_price($price);
        $variation->set_stock_quantity($stock);
        $variation->set_manage_stock(true);
        $variation->set_stock_status('instock');
        $variation->set_status('publish');
        $variation->save();
    }
}

function delete_all_products() {
    $products = wc_get_products(['limit' => -1, 'return' => 'ids']);
    $deleted = 0;

    foreach ($products as $product_id) {
        wp_delete_post($product_id, true);
        $deleted++;
        if ($deleted % 10 === 0) {
            echo "✓ $deleted produits supprimés...<br>";
            flush();
        }
    }

    echo "<div style='margin: 20px; font-family: Arial;'>";
    echo "<h2 style='color: green;'>✓ Suppression terminée !</h2>";
    echo "<p><strong>$deleted produits supprimés</strong></p>";
    echo "<p><a href='?'>← Retour</a></p>";
    echo "</div>";
}

function show_menu() {
    $product_count = wp_count_posts('product')->publish ?? 0;

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Ajout en masse de produits</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { max-width: 700px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            h1 { color: #333; }
            h2 { color: #555; margin-top: 30px; }
            .info { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
            .action { display: inline-block; margin: 10px 0; }
            a { display: inline-block; padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; margin-bottom: 10px; }
            a:hover { background: #1976D2; }
            a.danger { background: #f44336; }
            a.danger:hover { background: #da190b; }
            a.warning { background: #ff9800; }
            a.warning:hover { background: #f57c00; }
            input { padding: 8px; font-size: 16px; width: 80px; }
            .stats { background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #4CAF50; }
            .features { background: #fff9e6; padding: 15px; border-left: 4px solid #FFC107; margin: 20px 0; border-radius: 4px; }
            ul { margin: 10px 0; padding-left: 20px; }
            li { margin: 5px 0; }
            pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📦 Gestionnaire de produits de test (WordPress-Ravi)</h1>

            <div class="stats">
                <strong>📊 Produits actuels :</strong> <?php echo $product_count; ?>
            </div>

            <div class="features">
                <strong>✨ Fonctionnalités :</strong>
                <ul>
                    <li>✅ Catégories : Homme / Femme</li>
                    <li>✅ Attributs : Tailles (XS, S, M, L, XL, XXL)</li>
                    <li>✅ Images : Téléchargées depuis Unsplash</li>
                    <li>✅ Variations : Produits variables par taille</li>
                </ul>
            </div>

            <h2>🚀 Configuration initiale</h2>
            <p>À faire une seule fois pour créer les catégories et tailles :</p>
            <div class="action">
                <a class="warning" href="?action=setup">⚙️ Configurer catégories & tailles</a>
            </div>

            <h2>➕ Ajouter des produits</h2>
            <div class="action">
                <label>Nombre de produits : <input type="number" id="count" value="20" min="1" max="500"></label>
                <a href="#" onclick="createProducts()">➕ Créer</a>
            </div>
            <p style="font-size: 12px; color: #999;">Chaque produit = 1 produit variable avec 6 tailles (XS-XXL)</p>

            <h2>🗑️ Supprimer tous les produits</h2>
            <div class="action">
                <a class="danger" href="#" onclick="if(confirm('Êtes-vous sûr ? Cela supprimera TOUS les produits !')) { window.location='?action=delete'; }">🗑️ Supprimer tous</a>
            </div>

            <hr style="margin: 30px 0;">

            <h2>🧪 Tester l'API et la pagination</h2>
            <div class="info">
                Commandes utiles pour tester l'API avec cURL :
            </div>
            <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; border-radius: 4px; overflow-x: auto;">
<pre>
# Page 1 - 10 produits (défaut)
curl "http://localhost:3000/api/products?page=1"

# Page 2
curl "http://localhost:3000/api/products?page=2"

# 20 produits par page
curl "http://localhost:3000/api/products?page=1&limit=20"

# 50 produits par page
curl "http://localhost:3000/api/products?page=1&limit=50"

# Filtre par catégorie
curl "http://localhost:3000/api/products?category=homme&page=1"

# Récupérer le nombre total
curl "http://localhost:3000/api/products?page=1" | jq '.pagination.total'
</pre>
            </div>

            <h2>📱 Frontend pour tester</h2>
            <ul>
                <li><a href="http://localhost:3000" target="_blank">Page d'accueil (voir les produits)</a></li>
                <li><a href="http://localhost:3000/shop" target="_blank">Page shop</a></li>
            </ul>
        </div>

        <script>
            function createProducts() {
                const count = document.getElementById('count').value;
                if (count < 1 || count > 500) {
                    alert('Entre 1 et 500 produits');
                    return;
                }
                if (confirm('Cela va créer ' + count + ' produits. Êtes-vous sûr ?')) {
                    window.location = '?action=create&count=' + count;
                }
            }
        </script>
    </body>
    </html>
    <?php
}
?>
