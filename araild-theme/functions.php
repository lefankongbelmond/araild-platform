<?php
/**
 * ARAILD — Thème Institutionnel
 * functions.php
 */
if (!defined('ABSPATH')) exit;

define('ARAILD_VERSION', '1.0.0');

/* =========================================================
 * 1. THEME SETUP
 * ======================================================= */
function araild_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('custom-logo', [
        'height'      => 80,
        'width'       => 240,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('align-wide');
    add_theme_support('responsive-embeds');
    add_image_size('araild-hero', 1920, 800, true);
    add_image_size('araild-card', 600, 400, true);
    add_image_size('araild-thumb', 300, 300, true);
    register_nav_menus([
        'primary' => __('Menu Principal', 'araild'),
        'footer'  => __('Menu Footer', 'araild'),
    ]);
    load_theme_textdomain('araild', get_template_directory() . '/languages');
}
add_action('after_setup_theme', 'araild_setup');

function araild_content_width() {
    $GLOBALS['content_width'] = 1200;
}
add_action('after_setup_theme', 'araild_content_width', 0);

/* =========================================================
 * 2. ENQUEUE SCRIPTS & STYLES
 * ======================================================= */
function araild_assets() {
    // Google Fonts
    wp_enqueue_style('araild-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap', [], null);
    wp_enqueue_style('araild-style', get_stylesheet_uri(), ['araild-fonts'], ARAILD_VERSION);
    wp_enqueue_script('araild-js', get_template_directory_uri() . '/assets/js/araild.js', [], ARAILD_VERSION, true);

    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
    // Inline brand colors from customizer
    $primary = sanitize_hex_color(get_theme_mod('araild_color_primary', '#6a1b9a'));
    $accent  = sanitize_hex_color(get_theme_mod('araild_color_accent', '#e91e63'));
    $css = ":root{--primary:{$primary};--accent:{$accent};}";
    wp_add_inline_style('araild-style', $css);
}
add_action('wp_enqueue_scripts', 'araild_assets');

function araild_preconnect($urls, $relation_type) {
    if ('preconnect' === $relation_type) {
        $urls[] = ['href' => 'https://fonts.googleapis.com'];
        $urls[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => true];
    }
    return $urls;
}
add_filter('wp_resource_hints', 'araild_preconnect', 10, 2);

/* =========================================================
 * 3. CUSTOM POST TYPES
 * ======================================================= */
function araild_register_cpts() {
    $cpts = [
        'araild_membre'     => ['Membre', 'Membres', 'dashicons-groups', 'membre'],
        'araild_partenaire' => ['Partenaire', 'Partenaires', 'dashicons-networking', 'partenaire'],
        'araild_projet'     => ['Projet', 'Projets', 'dashicons-portfolio', 'projet'],
        'araild_document'   => ['Document', 'Documents', 'dashicons-media-document', 'document'],
        'araild_resultat'   => ['Résultat', 'Résultats', 'dashicons-chart-bar', 'resultat'],
        'araild_axe'        => ['Axe', 'Axes', 'dashicons-category', 'axe'],
        'araild_valeur'     => ['Valeur', 'Valeurs', 'dashicons-heart', 'valeur'],
        'araild_faq'        => ['FAQ', 'FAQ', 'dashicons-editor-help', 'faq'],
    ];
    foreach ($cpts as $slug => $d) {
        list($single, $plural, $icon, $rewrite) = $d;
        register_post_type($slug, [
            'labels' => [
                'name'          => $plural,
                'singular_name' => $single,
                'add_new_item'  => sprintf(__('Ajouter %s', 'araild'), $single),
                'edit_item'     => sprintf(__('Modifier %s', 'araild'), $single),
                'all_items'     => $plural,
                'menu_name'     => $plural,
            ],
            'public'       => true,
            'has_archive'  => true,
            'menu_icon'    => $icon,
            'supports'     => ['title', 'editor', 'thumbnail', 'page-attributes', 'excerpt'],
            'rewrite'      => ['slug' => $rewrite],
            'show_in_rest' => true,
        ]);
    }
}
add_action('init', 'araild_register_cpts');

/* =========================================================
 * 4. META BOXES
 * ======================================================= */
function araild_meta_fields() {
    return [
        'araild_membre' => [
            '_araild_role'     => ['label' => 'Rôle / Fonction', 'type' => 'text'],
            '_araild_ordre'    => ['label' => 'Ordre d\'affichage', 'type' => 'number'],
        ],
        'araild_partenaire' => [
            '_araild_url'      => ['label' => 'Site web', 'type' => 'url'],
            '_araild_niveau'   => ['label' => 'Niveau (strategique/technique/financier)', 'type' => 'text'],
            '_araild_logo'     => ['label' => 'Logo (médiathèque)', 'type' => 'media'],
        ],
        'araild_projet' => [
            '_araild_statut'   => ['label' => 'Statut (En cours/Terminé/À venir)', 'type' => 'text'],
            '_araild_axe'      => ['label' => 'Axe associé', 'type' => 'text'],
            '_araild_avancement'=> ['label' => 'Avancement (%)', 'type' => 'number'],
            '_araild_lieu'     => ['label' => 'Lieu', 'type' => 'text'],
        ],
        'araild_document' => [
            '_araild_fichier'  => ['label' => 'Fichier (médiathèque)', 'type' => 'media'],
            '_araild_categorie'=> ['label' => 'Catégorie', 'type' => 'text'],
        ],
        'araild_resultat' => [
            '_araild_chiffre'  => ['label' => 'Chiffre', 'type' => 'text'],
            '_araild_domaine'  => ['label' => 'Domaine', 'type' => 'text'],
            '_araild_icone'    => ['label' => 'Icône (emoji)', 'type' => 'text'],
        ],
        'araild_axe' => [
            '_araild_numero'   => ['label' => 'Numéro de l\'axe', 'type' => 'number'],
            '_araild_icone'    => ['label' => 'Icône (emoji)', 'type' => 'text'],
            '_araild_resume'   => ['label' => 'Résumé court', 'type' => 'textarea'],
            '_araild_lien'     => ['label' => 'Lien page détail', 'type' => 'url'],
        ],
        'araild_valeur' => [
            '_araild_icone'    => ['label' => 'Icône (emoji)', 'type' => 'text'],
            '_araild_couleur'  => ['label' => 'Couleur', 'type' => 'text'],
        ],
        'araild_faq' => [
            '_araild_reponse'  => ['label' => 'Réponse (sinon utilise le contenu)', 'type' => 'textarea'],
        ],
    ];
}

function araild_add_meta_boxes() {
    foreach (araild_meta_fields() as $cpt => $fields) {
        add_meta_box('araild_'.$cpt.'_meta', __('Détails ARAILD', 'araild'), 'araild_render_meta_box', $cpt, 'normal', 'high', ['fields' => $fields]);
    }
    // Meta box pour la page Formation Femmes Petits Métiers
    add_meta_box('araild_pff_meta', __('📷 Contenu & Photos — Formation Femmes Petits Métiers', 'araild'), 'araild_render_pff_meta_box', 'page', 'normal', 'high');
}
add_action('add_meta_boxes', 'araild_add_meta_boxes');

function araild_render_pff_meta_box($post) {
    if (get_page_template_slug($post->ID) !== 'page-formation-femmes.php') {
        echo '<p style="color:#999;padding:8px 0">' . esc_html__('Cette meta box s\'active uniquement pour le template "Formation Femmes Petits Métiers".', 'araild') . '</p>';
        return;
    }
    wp_nonce_field('araild_pff_save', 'araild_pff_meta_nonce');

    $fields_groups = [
        __('🖼️ Photos principales', 'araild') => [
            '_pff_hero_img'   => ['label' => __('Photo Hero (bannière principale)', 'araild'),      'type' => 'media'],
            '_pff_s1_img'     => ['label' => __('Photo Section 1 (Pourquoi cette formation)', 'araild'), 'type' => 'media'],
            '_pff_s2_img'     => ['label' => __('Photo Section 2 (Témoignages)', 'araild'),         'type' => 'media'],
            '_pff_s3_img'     => ['label' => __('Photo Section 3 (Conditions d\'accès)', 'araild'), 'type' => 'media'],
        ],
        __('🎨 Galerie photos', 'araild') => [
            '_pff_galerie'         => ['label' => __('IDs médiathèque séparés par virgule (ex: 12,15,18)', 'araild'), 'type' => 'text'],
            '_pff_galerie_titre'   => ['label' => __('Titre de la galerie', 'araild'), 'type' => 'text'],
        ],
        __('📝 Textes éditables', 'araild') => [
            '_pff_sous_titre'  => ['label' => __('Sous-titre hero', 'araild'), 'type' => 'text'],
            '_pff_b1_eyebrow'  => ['label' => __('Section 1 — Eyebrow (petit texte au-dessus)', 'araild'), 'type' => 'text'],
            '_pff_b1_titre'    => ['label' => __('Section 1 — Titre', 'araild'), 'type' => 'text'],
            '_pff_b1_texte'    => ['label' => __('Section 1 — Paragraphe principal', 'araild'), 'type' => 'textarea'],
            '_pff_b2_eyebrow'  => ['label' => __('Section Modules — Eyebrow', 'araild'), 'type' => 'text'],
            '_pff_b2_titre'    => ['label' => __('Section Modules — Titre', 'araild'), 'type' => 'text'],
            '_pff_b2_sous_titre'=> ['label' => __('Section Modules — Sous-titre', 'araild'), 'type' => 'text'],
            '_pff_b3_eyebrow'  => ['label' => __('Section Témoignages — Eyebrow', 'araild'), 'type' => 'text'],
            '_pff_b3_titre'    => ['label' => __('Section Témoignages — Titre', 'araild'), 'type' => 'text'],
            '_pff_b4_eyebrow'  => ['label' => __('Section Conditions — Eyebrow', 'araild'), 'type' => 'text'],
            '_pff_b4_titre'    => ['label' => __('Section Conditions — Titre', 'araild'), 'type' => 'text'],
            '_pff_insc_titre'  => ['label' => __('Formulaire — Titre', 'araild'), 'type' => 'text'],
        ],
        __('📊 Statistiques (Section 1)', 'araild') => [
            '_pff_stat1'       => ['label' => __('Chiffre 1 (ex: 500+)', 'araild'), 'type' => 'text'],
            '_pff_stat1_label' => ['label' => __('Label chiffre 1', 'araild'), 'type' => 'text'],
            '_pff_stat2'       => ['label' => __('Chiffre 2', 'araild'), 'type' => 'text'],
            '_pff_stat2_label' => ['label' => __('Label chiffre 2', 'araild'), 'type' => 'text'],
            '_pff_stat3'       => ['label' => __('Chiffre 3', 'araild'), 'type' => 'text'],
            '_pff_stat3_label' => ['label' => __('Label chiffre 3', 'araild'), 'type' => 'text'],
        ],
        __('🧵 6 Modules de formation', 'araild') => [
            '_pff_m1_icone' => ['label' => __('Module 1 — Icône (emoji)', 'araild'), 'type' => 'text'],
            '_pff_m1_nom'   => ['label' => __('Module 1 — Nom', 'araild'), 'type' => 'text'],
            '_pff_m1_desc'  => ['label' => __('Module 1 — Description', 'araild'), 'type' => 'textarea'],
            '_pff_m1_color' => ['label' => __('Module 1 — Couleur hex (ex: #6a1b9a)', 'araild'), 'type' => 'text'],
            '_pff_m1_duree' => ['label' => __('Module 1 — Durée (ex: 4 semaines)', 'araild'), 'type' => 'text'],
            '_pff_m2_icone' => ['label' => __('Module 2 — Icône', 'araild'), 'type' => 'text'],
            '_pff_m2_nom'   => ['label' => __('Module 2 — Nom', 'araild'), 'type' => 'text'],
            '_pff_m2_desc'  => ['label' => __('Module 2 — Description', 'araild'), 'type' => 'textarea'],
            '_pff_m2_color' => ['label' => __('Module 2 — Couleur hex', 'araild'), 'type' => 'text'],
            '_pff_m2_duree' => ['label' => __('Module 2 — Durée', 'araild'), 'type' => 'text'],
            '_pff_m3_icone' => ['label' => __('Module 3 — Icône', 'araild'), 'type' => 'text'],
            '_pff_m3_nom'   => ['label' => __('Module 3 — Nom', 'araild'), 'type' => 'text'],
            '_pff_m3_desc'  => ['label' => __('Module 3 — Description', 'araild'), 'type' => 'textarea'],
            '_pff_m3_color' => ['label' => __('Module 3 — Couleur hex', 'araild'), 'type' => 'text'],
            '_pff_m3_duree' => ['label' => __('Module 3 — Durée', 'araild'), 'type' => 'text'],
            '_pff_m4_icone' => ['label' => __('Module 4 — Icône', 'araild'), 'type' => 'text'],
            '_pff_m4_nom'   => ['label' => __('Module 4 — Nom', 'araild'), 'type' => 'text'],
            '_pff_m4_desc'  => ['label' => __('Module 4 — Description', 'araild'), 'type' => 'textarea'],
            '_pff_m4_color' => ['label' => __('Module 4 — Couleur hex', 'araild'), 'type' => 'text'],
            '_pff_m4_duree' => ['label' => __('Module 4 — Durée', 'araild'), 'type' => 'text'],
            '_pff_m5_icone' => ['label' => __('Module 5 — Icône', 'araild'), 'type' => 'text'],
            '_pff_m5_nom'   => ['label' => __('Module 5 — Nom', 'araild'), 'type' => 'text'],
            '_pff_m5_desc'  => ['label' => __('Module 5 — Description', 'araild'), 'type' => 'textarea'],
            '_pff_m5_color' => ['label' => __('Module 5 — Couleur hex', 'araild'), 'type' => 'text'],
            '_pff_m5_duree' => ['label' => __('Module 5 — Durée', 'araild'), 'type' => 'text'],
            '_pff_m6_icone' => ['label' => __('Module 6 — Icône', 'araild'), 'type' => 'text'],
            '_pff_m6_nom'   => ['label' => __('Module 6 — Nom', 'araild'), 'type' => 'text'],
            '_pff_m6_desc'  => ['label' => __('Module 6 — Description', 'araild'), 'type' => 'textarea'],
            '_pff_m6_color' => ['label' => __('Module 6 — Couleur hex', 'araild'), 'type' => 'text'],
            '_pff_m6_duree' => ['label' => __('Module 6 — Durée', 'araild'), 'type' => 'text'],
        ],
        __('💬 Témoignages (max 3)', 'araild') => [
            '_pff_t1_cite' => ['label' => __('Témoignage 1 — Citation', 'araild'), 'type' => 'textarea'],
            '_pff_t1_nom'  => ['label' => __('Témoignage 1 — Nom', 'araild'), 'type' => 'text'],
            '_pff_t1_lieu' => ['label' => __('Témoignage 1 — Module/Lieu', 'araild'), 'type' => 'text'],
            '_pff_t2_cite' => ['label' => __('Témoignage 2 — Citation', 'araild'), 'type' => 'textarea'],
            '_pff_t2_nom'  => ['label' => __('Témoignage 2 — Nom', 'araild'), 'type' => 'text'],
            '_pff_t2_lieu' => ['label' => __('Témoignage 2 — Module/Lieu', 'araild'), 'type' => 'text'],
            '_pff_t3_cite' => ['label' => __('Témoignage 3 — Citation', 'araild'), 'type' => 'textarea'],
            '_pff_t3_nom'  => ['label' => __('Témoignage 3 — Nom', 'araild'), 'type' => 'text'],
            '_pff_t3_lieu' => ['label' => __('Témoignage 3 — Module/Lieu', 'araild'), 'type' => 'text'],
        ],
        __('✅ Conditions d\'accès', 'araild') => [
            '_pff_conditions' => ['label' => __('Liste des conditions (1 par ligne)', 'araild'), 'type' => 'textarea'],
        ],
    ];

    echo '<div style="display:grid;gap:24px;padding:8px 0">';
    foreach ($fields_groups as $group_label => $fields) {
        echo '<details open style="border:1px solid #ddd;border-radius:8px;padding:16px"><summary style="font-weight:700;font-size:1rem;cursor:pointer;margin-bottom:12px">' . esc_html($group_label) . '</summary>';
        echo '<div style="display:grid;gap:12px;margin-top:12px">';
        foreach ($fields as $key => $f) {
            $val = get_post_meta($post->ID, $key, true);
            $id  = esc_attr($key);
            echo '<div><label for="' . $id . '" style="display:block;font-weight:600;margin-bottom:4px;font-size:.9rem">' . esc_html($f['label']) . '</label>';
            if ($f['type'] === 'textarea') {
                echo '<textarea id="' . $id . '" name="' . $id . '" rows="3" style="width:100%">' . esc_textarea($val) . '</textarea>';
            } elseif ($f['type'] === 'media') {
                $url = $val ? esc_url(wp_get_attachment_image_url($val, 'thumbnail')) : '';
                echo '<input type="hidden" class="araild-media-id" id="' . $id . '" name="' . $id . '" value="' . esc_attr($val) . '">';
                echo '<div class="araild-media-preview" style="margin-bottom:6px">' . ($url ? '<img src="' . $url . '" style="max-height:80px;border-radius:6px">' : '') . '</div>';
                echo '<button type="button" class="button button-primary araild-media-btn">' . esc_html__('📷 Choisir une photo', 'araild') . '</button> ';
                echo '<button type="button" class="button araild-media-remove">' . esc_html__('Retirer', 'araild') . '</button>';
            } else {
                echo '<input type="text" id="' . $id . '" name="' . $id . '" value="' . esc_attr($val) . '" style="width:100%">';
            }
            echo '</div>';
        }
        echo '</div></details>';
    }
    echo '</div>';
}

function araild_save_pff_meta($post_id) {
    if (!isset($_POST['araild_pff_meta_nonce']) || !wp_verify_nonce(sanitize_key($_POST['araild_pff_meta_nonce']), 'araild_pff_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $text_keys = [
        '_pff_sous_titre','_pff_galerie','_pff_galerie_titre',
        '_pff_b1_eyebrow','_pff_b1_titre','_pff_b2_eyebrow','_pff_b2_titre','_pff_b2_sous_titre',
        '_pff_b3_eyebrow','_pff_b3_titre','_pff_b4_eyebrow','_pff_b4_titre','_pff_insc_titre',
        '_pff_stat1','_pff_stat1_label','_pff_stat2','_pff_stat2_label','_pff_stat3','_pff_stat3_label',
        '_pff_m1_icone','_pff_m1_nom','_pff_m1_color','_pff_m1_duree',
        '_pff_m2_icone','_pff_m2_nom','_pff_m2_color','_pff_m2_duree',
        '_pff_m3_icone','_pff_m3_nom','_pff_m3_color','_pff_m3_duree',
        '_pff_m4_icone','_pff_m4_nom','_pff_m4_color','_pff_m4_duree',
        '_pff_m5_icone','_pff_m5_nom','_pff_m5_color','_pff_m5_duree',
        '_pff_m6_icone','_pff_m6_nom','_pff_m6_color','_pff_m6_duree',
        '_pff_t1_nom','_pff_t1_lieu','_pff_t2_nom','_pff_t2_lieu','_pff_t3_nom','_pff_t3_lieu',
    ];
    $textarea_keys = [
        '_pff_b1_texte','_pff_conditions',
        '_pff_m1_desc','_pff_m2_desc','_pff_m3_desc','_pff_m4_desc','_pff_m5_desc','_pff_m6_desc',
        '_pff_t1_cite','_pff_t2_cite','_pff_t3_cite',
    ];
    $media_keys = ['_pff_hero_img','_pff_s1_img','_pff_s2_img','_pff_s3_img'];

    foreach ($text_keys as $k) {
        if (isset($_POST[$k])) update_post_meta($post_id, $k, sanitize_text_field(wp_unslash($_POST[$k])));
    }
    foreach ($textarea_keys as $k) {
        if (isset($_POST[$k])) update_post_meta($post_id, $k, sanitize_textarea_field(wp_unslash($_POST[$k])));
    }
    foreach ($media_keys as $k) {
        if (isset($_POST[$k])) update_post_meta($post_id, $k, absint($_POST[$k]));
    }
}
add_action('save_post_page', 'araild_save_pff_meta');

function araild_render_meta_box($post, $box) {
    $fields = $box['args']['fields'];
    wp_nonce_field('araild_save_meta', 'araild_meta_nonce');
    echo '<div style="display:grid;gap:14px;padding:8px 0">';
    foreach ($fields as $key => $f) {
        $val = get_post_meta($post->ID, $key, true);
        $id = esc_attr($key);
        echo '<div><label for="'.$id.'" style="display:block;font-weight:600;margin-bottom:4px">'.esc_html($f['label']).'</label>';
        if ($f['type'] === 'textarea') {
            echo '<textarea id="'.$id.'" name="'.$id.'" rows="3" style="width:100%">'.esc_textarea($val).'</textarea>';
        } elseif ($f['type'] === 'media') {
            $url = $val ? esc_url(wp_get_attachment_url($val)) : '';
            echo '<input type="hidden" class="araild-media-id" id="'.$id.'" name="'.$id.'" value="'.esc_attr($val).'">';
            echo '<div class="araild-media-preview" style="margin-bottom:6px">'.($url ? '<img src="'.$url.'" style="max-height:80px">' : '').'</div>';
            echo '<button type="button" class="button araild-media-btn">'.esc_html__('Choisir un média', 'araild').'</button> ';
            echo '<button type="button" class="button araild-media-remove">'.esc_html__('Retirer', 'araild').'</button>';
        } else {
            $type = in_array($f['type'], ['number','url']) ? $f['type'] : 'text';
            echo '<input type="'.esc_attr($type).'" id="'.$id.'" name="'.$id.'" value="'.esc_attr($val).'" style="width:100%">';
        }
        echo '</div>';
    }
    echo '</div>';
}

function araild_save_meta($post_id) {
    if (!isset($_POST['araild_meta_nonce']) || !wp_verify_nonce(sanitize_key($_POST['araild_meta_nonce']), 'araild_save_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $all = araild_meta_fields();
    $type = get_post_type($post_id);
    if (!isset($all[$type])) return;
    foreach ($all[$type] as $key => $f) {
        if (!isset($_POST[$key])) continue;
        $raw = wp_unslash($_POST[$key]);
        if ($f['type'] === 'textarea') {
            $clean = sanitize_textarea_field($raw);
        } elseif ($f['type'] === 'url') {
            $clean = esc_url_raw($raw);
        } elseif ($f['type'] === 'number' || $f['type'] === 'media') {
            $clean = absint($raw);
        } else {
            $clean = sanitize_text_field($raw);
        }
        update_post_meta($post_id, $key, $clean);
    }
}
add_action('save_post', 'araild_save_meta');

function araild_admin_media_script($hook) {
    if (in_array($hook, ['post.php', 'post-new.php'])) {
        wp_enqueue_media();
        $js = "jQuery(function($){
            $('.araild-media-btn').on('click',function(e){e.preventDefault();var b=$(this),w=wp.media({title:'Choisir un média',multiple:false});
            w.on('select',function(){var a=w.state().get('selection').first().toJSON();b.closest('div').find('.araild-media-id').val(a.id);var u=a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url;b.closest('div').find('.araild-media-preview').html('<img src=\"'+u+'\" style=\"max-height:80px\">');});w.open();});
            $('.araild-media-remove').on('click',function(e){e.preventDefault();var d=$(this).closest('div');d.find('.araild-media-id').val('');d.find('.araild-media-preview').html('');});
        });";
        wp_add_inline_script('jquery-core', $js);
    }
}
add_action('admin_enqueue_scripts', 'araild_admin_media_script');

/* =========================================================
 * 5. CUSTOMIZER
 * ======================================================= */
function araild_customize_register($wp) {
    $wp->add_panel('araild_panel', ['title' => __('ARAILD — Paramètres du site', 'araild'), 'priority' => 10]);

    $add_text = function($wp, $section, $id, $label, $default = '', $type = 'text') {
        $wp->add_setting($id, ['default' => $default, 'sanitize_callback' => $type === 'url' ? 'esc_url_raw' : ($type === 'textarea' ? 'sanitize_textarea_field' : 'sanitize_text_field'), 'transport' => 'refresh']);
        $wp->add_control($id, ['label' => $label, 'section' => $section, 'type' => $type]);
    };
    $add_check = function($wp, $section, $id, $label, $default = true) {
        $wp->add_setting($id, ['default' => $default, 'sanitize_callback' => 'araild_sanitize_checkbox']);
        $wp->add_control($id, ['label' => $label, 'section' => $section, 'type' => 'checkbox']);
    };

    // Coordonnées
    $wp->add_section('araild_contact', ['title' => __('Coordonnées & Contact', 'araild'), 'panel' => 'araild_panel']);
    $add_text($wp, 'araild_contact', 'araild_tel', __('Téléphone', 'araild'), '+237 650 70 83 30');
    $add_text($wp, 'araild_contact', 'araild_email', __('Email', 'araild'), 'contact@araild.com');
    $add_text($wp, 'araild_contact', 'araild_whatsapp', __('WhatsApp (chiffres)', 'araild'), '237650708330');
    $add_text($wp, 'araild_contact', 'araild_adresse', __('Adresse', 'araild'), 'Entrée CCO, Bafoussam, Cameroun');
    $add_text($wp, 'araild_contact', 'araild_antennes', __('Antennes', 'araild'), 'Yaoundé, Bamenda, Dschang');
    $add_text($wp, 'araild_contact', 'araild_horaires', __('Horaires', 'araild'), 'Lun–Ven : 8h–17h');

    // Réseaux sociaux
    $wp->add_section('araild_social', ['title' => __('Réseaux sociaux', 'araild'), 'panel' => 'araild_panel']);
    foreach (['facebook','linkedin','youtube','twitter','instagram'] as $net) {
        $add_text($wp, 'araild_social', 'araild_'.$net, ucfirst($net), '', 'url');
    }

    // Couleurs
    $wp->add_section('araild_colors', ['title' => __('Couleurs de la marque', 'araild'), 'panel' => 'araild_panel']);
    $wp->add_setting('araild_color_primary', ['default' => '#6a1b9a', 'sanitize_callback' => 'sanitize_hex_color']);
    $wp->add_control(new WP_Customize_Color_Control($wp, 'araild_color_primary', ['label' => __('Couleur primaire', 'araild'), 'section' => 'araild_colors']));
    $wp->add_setting('araild_color_accent', ['default' => '#e91e63', 'sanitize_callback' => 'sanitize_hex_color']);
    $wp->add_control(new WP_Customize_Color_Control($wp, 'araild_color_accent', ['label' => __('Couleur accent', 'araild'), 'section' => 'araild_colors']));

    // Hero
    $wp->add_section('araild_hero', ['title' => __('Accueil → Hero', 'araild'), 'panel' => 'araild_panel']);
    $add_text($wp, 'araild_hero', 'araild_hero_titre', __('Titre', 'araild'), 'Ensemble pour un développement durable et inclusif');
    $add_text($wp, 'araild_hero', 'araild_hero_slogan', __('Slogan', 'araild'), 'ARAILD — Action pour la Recherche et l\'Appui aux Initiatives Locales de Développement');
    $add_text($wp, 'araild_hero', 'araild_hero_description', __('Description', 'araild'), 'Depuis 2018, nous œuvrons pour l\'autonomisation des communautés locales au Cameroun.', 'textarea');
    $add_text($wp, 'araild_hero', 'araild_annee', __('Année de fondation', 'araild'), '2018');
    $add_text($wp, 'araild_hero', 'araild_cta1_label', __('CTA 1 — libellé', 'araild'), 'Nos projets');
    $add_text($wp, 'araild_hero', 'araild_cta1_link', __('CTA 1 — lien', 'araild'), '/projets', 'url');
    $add_text($wp, 'araild_hero', 'araild_cta2_label', __('CTA 2 — libellé', 'araild'), 'Nous soutenir');
    $add_text($wp, 'araild_hero', 'araild_cta2_link', __('CTA 2 — lien', 'araild'), '/nous-soutenir', 'url');
    $stats = [['Bénéficiaires','5000+'],['Communautés','80+'],['Projets','30+'],['Partenaires','15+']];
    foreach ($stats as $i => $s) {
        $n = $i + 1;
        $add_text($wp, 'araild_hero', "araild_stat{$n}_label", "Stat $n — libellé", $s[0]);
        $add_text($wp, 'araild_hero', "araild_stat{$n}_value", "Stat $n — valeur", $s[1]);
    }
    $wp->add_setting('araild_hero_bg', ['default' => '', 'sanitize_callback' => 'absint']);
    $wp->add_control(new WP_Customize_Image_Control($wp, 'araild_hero_bg', ['label' => __('Image de fond du Hero', 'araild'), 'section' => 'araild_hero']));

    // Sections accueil
    $sections = [
        'axes'        => ['Axes', 'Nos axes d\'intervention', 'Quatre domaines d\'action pour un impact durable.'],
        'valeurs'     => ['Valeurs', 'Nos valeurs', 'Les principes qui guident notre action.'],
        'impact'      => ['Impact', 'Notre impact en chiffres', 'Des résultats concrets sur le terrain.'],
        'projets'     => ['Projets vedette', 'Projets en vedette', 'Découvrez nos initiatives phares.'],
        'partenaires' => ['Partenaires', 'Nos partenaires', 'Ils nous font confiance.'],
        'equipe'      => ['Équipe', 'Notre équipe', 'Des femmes et des hommes engagés.'],
        'cta'         => ['CTA final', '', ''],
    ];
    foreach ($sections as $key => $s) {
        $wp->add_section("araild_sec_$key", ['title' => 'Accueil → ' . $s[0], 'panel' => 'araild_panel']);
        $add_check($wp, "araild_sec_$key", "araild_show_$key", __('Afficher cette section', 'araild'), true);
        if ($key !== 'cta') {
            $add_text($wp, "araild_sec_$key", "araild_title_$key", __('Titre', 'araild'), $s[1]);
            $add_text($wp, "araild_sec_$key", "araild_intro_$key", __('Intro', 'araild'), $s[2], 'textarea');
        }
    }

    // Footer & CTA
    $wp->add_section('araild_footer', ['title' => __('Footer & CTA', 'araild'), 'panel' => 'araild_panel']);
    $add_text($wp, 'araild_footer', 'araild_footer_desc', __('Description footer', 'araild'), 'ARAILD est une ONG camerounaise qui œuvre pour le développement durable et l\'autonomisation des communautés locales.', 'textarea');
    $add_text($wp, 'araild_footer', 'araild_cta_titre', __('Titre CTA', 'araild'), 'Rejoignez notre mission');
    $add_text($wp, 'araild_footer', 'araild_cta_soustitre', __('Sous-titre CTA', 'araild'), 'Ensemble, construisons un avenir durable et inclusif pour les communautés du Cameroun.', 'textarea');
}
add_action('customize_register', 'araild_customize_register');

function araild_sanitize_checkbox($v) { return (bool) $v; }

/* =========================================================
 * 6. HELPERS
 * ======================================================= */
function araild_opt($key, $default = '') {
    return get_theme_mod('araild_' . $key, $default);
}
function araild_e($key, $default = '') {
    echo esc_html(araild_opt($key, $default));
}
function araild_show($key) {
    return (bool) get_theme_mod('araild_show_' . $key, true);
}
function araild_initials($name) {
    $words = preg_split('/\s+/', trim(wp_strip_all_tags($name)));
    $out = '';
    foreach (array_slice($words, 0, 2) as $w) { $out .= mb_strtoupper(mb_substr($w, 0, 1)); }
    return $out ?: 'A';
}

/* =========================================================
 * 7. CRÉATION AUTO DES PAGES + DÉMO
 * ======================================================= */
function araild_after_switch() {
    $pages = [
        'Accueil'        => 'front-page.php',
        'À propos'       => 'page-a-propos.php',
        'Vision & Mission'=> 'page-vision.php',
        'Nos valeurs'    => 'page-valeurs.php',
        'Notre équipe'   => 'page-equipe.php',
        'Axes d\'intervention' => 'page-axes.php',
        'Axe 1 — Autonomisation économique' => 'page-axe1.php',
        'Axe 2 — Santé, Éducation, Protection' => 'page-axe2.php',
        'Axe 3 — Résilience climatique' => 'page-axe3.php',
        'Axe 4 — Gouvernance & paix' => 'page-axe4.php',
        'Résultats'      => 'page-resultats.php',
        'Projets'        => 'page-projets.php',
        'Actualités'     => 'page-actualites.php',
        'Galerie'        => 'page-galerie.php',
        'Documentation'  => 'page-documentation.php',
        'Partenaires'    => 'page-partenaires.php',
        'ODD'            => 'page-odd.php',
        'Nous soutenir'  => 'page-nous-soutenir.php',
        'Contact'        => 'page-contact.php',
        'FAQ'            => 'page-faq.php',
        'Formation en ligne' => 'page-formation.php',
        'Bibliothèque'   => 'page-bibliotheque.php',
        'Formation Femmes Petits Métiers' => 'page-formation-femmes.php',
    ];
    $accueil_id = 0;
    foreach ($pages as $title => $template) {
        $existing = get_page_by_path(sanitize_title($title));
        if (!$existing) {
            $id = wp_insert_post([
                'post_title'  => $title,
                'post_status' => 'publish',
                'post_type'   => 'page',
                'post_content'=> '',
            ]);
        } else {
            $id = $existing->ID;
        }
        if ($id && !is_wp_error($id)) {
            update_post_meta($id, '_wp_page_template', $template);
            if ($template === 'front-page.php') $accueil_id = $id;
        }
    }
    if ($accueil_id) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $accueil_id);
        $blog = get_page_by_path('actualites');
        if ($blog) update_option('page_for_posts', $blog->ID);
    }

    // Catégories articles
    foreach (['Communiqués', 'Événements', 'Sur le terrain', 'Partenariats'] as $cat) {
        if (!term_exists($cat, 'category')) wp_insert_term($cat, 'category');
    }

    araild_seed_demo();

    // Menu principal
    araild_seed_menu($pages);

    flush_rewrite_rules();
}
add_action('after_switch_theme', 'araild_after_switch');

function araild_seed_post($type, $title, $content, $meta = [], $order = 0) {
    $exists = get_posts(['post_type' => $type, 'title' => $title, 'numberposts' => 1, 'post_status' => 'any']);
    if ($exists) return $exists[0]->ID;
    $id = wp_insert_post([
        'post_type'   => $type,
        'post_title'  => $title,
        'post_content'=> $content,
        'post_status' => 'publish',
        'menu_order'  => $order,
    ]);
    if ($id && !is_wp_error($id)) {
        foreach ($meta as $k => $v) update_post_meta($id, $k, $v);
        return $id;
    }
    return 0;
}

function araild_seed_demo() {
    // MEMBRES
    $membres = [
        'Président du Conseil d\'Administration',
        'Directeur Exécutif',
        'Directeur des Programmes',
        'Chargé de Mission',
        'Comptable',
        'Responsable Programme Résilience Climatique et Transition Verte',
    ];
    foreach ($membres as $i => $role) {
        araild_seed_post('araild_membre', $role, 'Membre de l\'équipe ARAILD.', ['_araild_role' => $role, '_araild_ordre' => $i], $i);
    }

    // AXES
    $axes = [
        [1, '🌾', 'Axe 1 — Autonomisation économique', 'Renforcement des capacités économiques des communautés locales : appui aux activités génératrices de revenus, formation professionnelle, entrepreneuriat des jeunes et des femmes, accès au financement et structuration des filières agricoles.', 'Soutien à l\'entrepreneuriat local, formation, AGR et accès au financement.'],
        [2, '🏥', 'Axe 2 — Santé, Éducation, Protection', 'Amélioration de l\'accès aux services sociaux de base : santé communautaire, sensibilisation sanitaire (VIH/SIDA, santé oculaire), éducation, protection des groupes vulnérables et promotion des droits humains.', 'Santé communautaire, éducation, protection des personnes vulnérables.'],
        [3, '🌍', 'Axe 3 — Résilience climatique', 'Adaptation aux changements climatiques et transition verte : agroécologie, production d\'engrais biologiques, reboisement, gestion durable des ressources naturelles et sensibilisation environnementale.', 'Agroécologie, transition verte et adaptation au changement climatique.'],
        [4, '⚖️', 'Axe 4 — Gouvernance & paix', 'Promotion de la bonne gouvernance, de la paix et de la cohésion sociale : renforcement des organisations de la société civile, formation à la gestion associative, fiscalité des OBNL et participation citoyenne.', 'Bonne gouvernance, renforcement de la société civile et cohésion sociale.'],
    ];
    foreach ($axes as $a) {
        araild_seed_post('araild_axe', $a[2], $a[3], ['_araild_numero' => $a[0], '_araild_icone' => $a[1], '_araild_resume' => $a[4], '_araild_lien' => '/axe' . $a[0]], $a[0]);
    }

    // RÉSULTATS
    $resultats = [
        ['115', 'causeries éducatives VIH/SIDA', 'Santé', '🏥'],
        ['222', 'consultations ophtalmologiques gratuites', 'Santé', '👁️'],
        ['96', 'colis de soins distribués', 'Santé', '📦'],
        ['560', 'personnes sensibilisées aux maladies oculaires', 'Santé', '👀'],
        ['6', 'cas VIH détectés et pris en charge', 'Santé', '❤️'],
        ['21', 'personnes formées à la production d\'engrais biologiques', 'Environnement', '🌱'],
        ['25', 'jeunes filles formées au greffage de plantes fruitières', 'Environnement', '🌿'],
        ['16', 'organisations formées à la fiscalité des OBNL', 'Gouvernance', '📊'],
        ['2', 'ateliers comptabilité/fiscalité pour l\'ESS', 'Gouvernance', '💼'],
    ];
    foreach ($resultats as $i => $r) {
        araild_seed_post('araild_resultat', $r[0] . ' ' . $r[1], '', ['_araild_chiffre' => $r[0], '_araild_domaine' => $r[2], '_araild_icone' => $r[3]], $i);
    }

    // PARTENAIRES
    $partenaires = [
        ['MINEPDED', 'strategique'], ['MINJEC', 'strategique'], ['MINSANTE', 'strategique'],
        ['MINAS', 'strategique'], ['Commission Nationale des Droits de l\'Homme', 'strategique'],
        ['CARE International', 'technique'], ['Friedrich Ebert Stiftung (FES)', 'financier'],
        ['Care and Health Program (CHP)', 'technique'],
    ];
    foreach ($partenaires as $i => $p) {
        araild_seed_post('araild_partenaire', $p[0], '', ['_araild_niveau' => $p[1]], $i);
    }

    // VALEURS
    $valeurs = [
        ['Solidarité', '💪', '#6a1b9a', 'Nous croyons en l\'entraide et la mutualisation des forces au service des plus vulnérables.'],
        ['Équité & Inclusion', '⚖️', '#2962ff', 'Nous garantissons l\'égalité des chances et l\'inclusion de tous, sans discrimination.'],
        ['Durabilité', '🌱', '#2e7d32', 'Nous inscrivons nos actions dans la durée et le respect de l\'environnement.'],
        ['Innovation sociale', '💡', '#e91e63', 'Nous développons des solutions créatives adaptées aux réalités locales.'],
        ['Engagement communautaire', '🤝', '#f5c233', 'Nous plaçons les communautés au cœur de la conception et de la mise en œuvre.'],
    ];
    foreach ($valeurs as $i => $v) {
        araild_seed_post('araild_valeur', $v[0], $v[3], ['_araild_icone' => $v[1], '_araild_couleur' => $v[2]], $i);
    }

    // PROJETS
    $projets = [
        ['Santé oculaire pour tous', 'Campagne de consultations ophtalmologiques gratuites et de sensibilisation aux maladies oculaires dans les communautés rurales.', 'Terminé', 'Axe 2', '100', 'Région de l\'Ouest'],
        ['Jeunes & agroécologie', 'Formation des jeunes filles au greffage de plantes fruitières et à la production d\'engrais biologiques.', 'En cours', 'Axe 3', '70', 'Bafoussam'],
        ['Gouvernance des OBNL', 'Renforcement des capacités des organisations de la société civile en fiscalité et comptabilité.', 'En cours', 'Axe 4', '55', 'Bafoussam'],
    ];
    foreach ($projets as $i => $p) {
        araild_seed_post('araild_projet', $p[0], $p[1], ['_araild_statut' => $p[2], '_araild_axe' => $p[3], '_araild_avancement' => $p[4], '_araild_lieu' => $p[5]], $i);
    }

    // DOCUMENTS
    $docs = [
        ['Rapport annuel 2023', 'Rapports'],
        ['Statuts de l\'association', 'Statutaires'],
        ['Plan stratégique 2024-2026', 'Stratégie'],
        ['Brochure de présentation', 'Communication'],
    ];
    foreach ($docs as $i => $d) {
        araild_seed_post('araild_document', $d[0], '', ['_araild_categorie' => $d[1]], $i);
    }

    // FAQ
    $faq = [
        ['Qu\'est-ce que l\'ARAILD ?', 'ARAILD (Action pour la Recherche et l\'Appui aux Initiatives Locales de Développement) est une ONG camerounaise créée en 2018 et enregistrée en mai 2019, qui œuvre pour le développement durable et l\'autonomisation des communautés locales.'],
        ['Dans quelles régions intervenez-vous ?', 'Notre siège est à Bafoussam, et nous disposons d\'antennes à Yaoundé, Bamenda et Dschang. Nous intervenons principalement dans l\'Ouest et le Nord-Ouest du Cameroun.'],
        ['Comment puis-je soutenir l\'ARAILD ?', 'Vous pouvez nous soutenir par un don, un partenariat, du bénévolat ou en relayant nos actions. Rendez-vous sur la page « Nous soutenir » pour découvrir toutes les modalités.'],
        ['Comment devenir partenaire ?', 'Contactez-nous via le formulaire de contact ou par email à contact@araild.com. Nous étudions chaque proposition de partenariat stratégique, technique ou financier.'],
        ['Vos actions sont-elles transparentes ?', 'Oui. Nous publions nos rapports annuels et documents dans la section Documentation, et nous appliquons des standards rigoureux de gouvernance et de redevabilité.'],
        ['Puis-je faire du bénévolat ?', 'Absolument. Nous accueillons régulièrement des bénévoles sur nos projets de terrain. Écrivez-nous pour connaître les opportunités en cours.'],
    ];
    foreach ($faq as $i => $f) {
        araild_seed_post('araild_faq', $f[0], $f[1], ['_araild_reponse' => $f[1]], $i);
    }
}

function araild_seed_menu($pages) {
    $menu_name = 'Menu Principal ARAILD';
    if (wp_get_nav_menu_object($menu_name)) return;
    $menu_id = wp_create_nav_menu($menu_name);
    if (is_wp_error($menu_id)) return;
    $items = ['Accueil', 'À propos', 'Axes d\'intervention', 'Projets', 'Résultats', 'Actualités', 'Partenaires', 'Contact'];
    foreach ($items as $title) {
        $page = get_page_by_path(sanitize_title($title));
        if ($page) {
            wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-title'     => $title,
                'menu-item-object-id' => $page->ID,
                'menu-item-object'    => 'page',
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            ]);
        }
    }
    $locations = get_theme_mod('nav_menu_locations', []);
    $locations['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}

/* =========================================================
 * 8. HANDLERS FORMULAIRES
 * ======================================================= */
function araild_handle_forms() {
    if (empty($_POST['araild_form_action'])) return;
    $action = sanitize_key($_POST['araild_form_action']);

    if ($action === 'contact') {
        if (!isset($_POST['araild_contact_nonce']) || !wp_verify_nonce(sanitize_key($_POST['araild_contact_nonce']), 'araild_contact')) {
            wp_safe_redirect(add_query_arg('contact', 'error', wp_get_referer())); exit;
        }
        $name = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $subject = sanitize_text_field(wp_unslash($_POST['sujet'] ?? 'Contact site ARAILD'));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        if (!$name || !is_email($email) || !$message) {
            wp_safe_redirect(add_query_arg('contact', 'error', wp_get_referer())); exit;
        }
        $to = araild_opt('email', get_option('admin_email'));
        $body = "Nom: $name\nEmail: $email\n\n$message";
        wp_mail($to, '[Contact ARAILD] ' . $subject, $body, ['Reply-To: ' . $email]);
        wp_safe_redirect(add_query_arg('contact', 'success', wp_get_referer())); exit;
    }

    if ($action === 'formation') {
        if (!isset($_POST['araild_formation_nonce']) || !wp_verify_nonce(sanitize_key($_POST['araild_formation_nonce']), 'araild_formation')) {
            wp_safe_redirect(add_query_arg('formation', 'error', wp_get_referer())); exit;
        }
        $name    = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $tel     = sanitize_text_field(wp_unslash($_POST['telephone'] ?? ''));
        $module  = sanitize_text_field(wp_unslash($_POST['module'] ?? ''));
        $format  = sanitize_text_field(wp_unslash($_POST['format'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        if (!$name || !is_email($email)) {
            wp_safe_redirect(add_query_arg('formation', 'error', wp_get_referer())); exit;
        }
        $to   = araild_opt('email', get_option('admin_email'));
        $body = "Nouvelle inscription formation\nNom : $name\nEmail : $email\nTél : $tel\nModule : $module\nFormat : $format\n\n$message";
        wp_mail($to, '[Formation ARAILD] ' . $module, $body, ['Reply-To: ' . $email]);
        wp_safe_redirect(add_query_arg('formation', 'success', wp_get_referer())); exit;
    }

    if ($action === 'pff_inscription') {
        if (!isset($_POST['araild_pff_nonce']) || !wp_verify_nonce(sanitize_key($_POST['araild_pff_nonce']), 'araild_pff_insc')) {
            wp_safe_redirect(add_query_arg('pff', 'error', wp_get_referer())); exit;
        }
        $name   = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        $tel    = sanitize_text_field(wp_unslash($_POST['telephone'] ?? ''));
        $email  = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $age    = absint($_POST['age'] ?? 0);
        $ville  = sanitize_text_field(wp_unslash($_POST['ville'] ?? ''));
        $module = sanitize_text_field(wp_unslash($_POST['module'] ?? ''));
        $format = sanitize_text_field(wp_unslash($_POST['format'] ?? ''));
        $msg    = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        if (!$name || !$tel || !$ville || !$module) {
            wp_safe_redirect(add_query_arg('pff', 'error', wp_get_referer())); exit;
        }
        $to   = araild_opt('email', get_option('admin_email'));
        $body = "Nouvelle inscription — Formation Femmes Petits Métiers\n\nNom : $name\nÂge : $age\nTéléphone : $tel\nEmail : $email\nVille : $ville\nModule souhaité : $module\nFormat : $format\n\nMessage :\n$msg";
        $headers = $email ? ['Reply-To: ' . $email] : [];
        wp_mail($to, '[ARAILD Femmes] Inscription — ' . $module, $body, $headers);
        wp_safe_redirect(add_query_arg('pff', 'success', wp_get_referer())); exit;
    }

    if ($action === 'soutien') {
        if (!isset($_POST['araild_soutien_nonce']) || !wp_verify_nonce(sanitize_key($_POST['araild_soutien_nonce']), 'araild_soutien')) {
            wp_safe_redirect(add_query_arg('soutien', 'error', wp_get_referer())); exit;
        }
        $name = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $type = sanitize_text_field(wp_unslash($_POST['type_soutien'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        if (!$name || !is_email($email)) {
            wp_safe_redirect(add_query_arg('soutien', 'error', wp_get_referer())); exit;
        }
        $to = araild_opt('email', get_option('admin_email'));
        $body = "Nouvelle proposition de soutien\nNom: $name\nEmail: $email\nType: $type\n\n$message";
        wp_mail($to, '[Soutien ARAILD] ' . $type, $body, ['Reply-To: ' . $email]);
        wp_safe_redirect(add_query_arg('soutien', 'success', wp_get_referer())); exit;
    }
}
add_action('template_redirect', 'araild_handle_forms');

/* =========================================================
 * 9. OPTIMISATIONS
 * ======================================================= */
function araild_excerpt_length() { return 24; }
add_filter('excerpt_length', 'araild_excerpt_length');
function araild_excerpt_more() { return '…'; }
add_filter('excerpt_more', 'araild_excerpt_more');
remove_action('wp_head', 'wp_generator');

/* Helper boucle réutilisable */
function araild_query($type, $number = -1, $orderby = 'menu_order title', $order = 'ASC') {
    return new WP_Query([
        'post_type'      => $type,
        'posts_per_page' => $number,
        'orderby'        => $orderby,
        'order'          => $order,
        'no_found_rows'  => true,
    ]);
}
