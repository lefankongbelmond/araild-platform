<?php
/* Template Name: Formation Femmes Petits Métiers */
if (!defined('ABSPATH')) exit;
get_header();

// Récupérer les photos de la page via méta
$pid        = get_the_ID();
$hero_img   = get_post_meta($pid, '_pff_hero_img', true);
$section1_img = get_post_meta($pid, '_pff_s1_img', true);
$section2_img = get_post_meta($pid, '_pff_s2_img', true);
$section3_img = get_post_meta($pid, '_pff_s3_img', true);
$galerie    = get_post_meta($pid, '_pff_galerie', true); // IDs séparés par virgule

$hero_url   = $hero_img   ? wp_get_attachment_image_url($hero_img, 'araild-hero')   : '';
$s1_url     = $section1_img ? wp_get_attachment_image_url($section1_img, 'araild-card') : '';
$s2_url     = $section2_img ? wp_get_attachment_image_url($section2_img, 'araild-card') : '';
$s3_url     = $section3_img ? wp_get_attachment_image_url($section3_img, 'araild-card') : '';
?>

<!-- ===== HERO ===== -->
<section class="page-hero" <?php if ($hero_url) : ?>style="background-image:url('<?php echo esc_url($hero_url); ?>');background-size:cover;background-position:center"<?php endif; ?>>
  <div class="pff-hero-overlay"></div>
  <div class="container" style="position:relative;z-index:2">
    <nav class="breadcrumb">
      <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> /
      <a href="<?php echo esc_url(home_url('/formation-en-ligne')); ?>"><?php esc_html_e('Formations', 'araild'); ?></a> /
      <?php esc_html_e('Femmes & Petits Métiers', 'araild'); ?>
    </nav>
    <h1><?php the_title(); ?></h1>
    <p><?php
      $sub = get_post_meta($pid, '_pff_sous_titre', true);
      echo $sub ? esc_html($sub) : esc_html__('Renforcer l\'autonomie économique des femmes à travers des formations pratiques et accessibles.', 'araild');
    ?></p>
    <div style="margin-top:28px;display:flex;gap:12px;flex-wrap:wrap">
      <a class="btn btn-primary btn-lg" href="#inscription"><?php esc_html_e('S\'inscrire gratuitement', 'araild'); ?></a>
      <a class="btn btn-outline" href="#modules"><?php esc_html_e('Voir les modules', 'araild'); ?></a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">

    <!-- Intro éditable depuis Pages -->
    <?php while (have_posts()) : the_post();
      if (trim(get_the_content())) : ?>
        <div class="entry-content fade-up" style="max-width:820px;margin:0 auto 56px"><?php the_content(); ?></div>
      <?php endif;
    endwhile; ?>

    <!-- ===== BLOC 1 : Pourquoi cette formation ===== -->
    <div class="pff-split fade-up" style="margin-bottom:72px">
      <div class="pff-split-text">
        <span class="eyebrow"><?php
          $label1 = get_post_meta($pid, '_pff_b1_eyebrow', true);
          echo $label1 ? esc_html($label1) : esc_html__('Pourquoi cette formation', 'araild');
        ?></span>
        <h2><?php
          $titre1 = get_post_meta($pid, '_pff_b1_titre', true);
          echo $titre1 ? esc_html($titre1) : esc_html__('L\'autonomie économique des femmes, notre priorité', 'araild');
        ?></h2>
        <div class="pff-content"><?php
          $texte1 = get_post_meta($pid, '_pff_b1_texte', true);
          echo $texte1 ? wp_kses_post(wpautop($texte1)) : '<p>' . esc_html__('Dans les zones rurales et semi-urbaines du Cameroun, de nombreuses femmes manquent d\'accès à des formations techniques adaptées à leurs besoins. ARAILD met en place des programmes pratiques pour les aider à créer et développer des activités génératrices de revenus dans les petits métiers.', 'araild') . '</p>';
        ?></div>
        <?php
        $chiffres = [
          get_post_meta($pid, '_pff_stat1', true) ?: '500+',
          get_post_meta($pid, '_pff_stat1_label', true) ?: __('Femmes formées', 'araild'),
          get_post_meta($pid, '_pff_stat2', true) ?: '12',
          get_post_meta($pid, '_pff_stat2_label', true) ?: __('Régions couvertes', 'araild'),
          get_post_meta($pid, '_pff_stat3', true) ?: '85%',
          get_post_meta($pid, '_pff_stat3_label', true) ?: __('Taux de réinsertion', 'araild'),
        ];
        ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:28px">
          <?php for ($i = 0; $i < 6; $i += 2) : ?>
            <div class="card" style="text-align:center;padding:20px 12px">
              <div style="font-size:1.8rem;font-weight:800;color:var(--primary)"><?php echo esc_html($chiffres[$i]); ?></div>
              <div style="font-size:.8rem;color:var(--muted);margin-top:4px"><?php echo esc_html($chiffres[$i+1]); ?></div>
            </div>
          <?php endfor; ?>
        </div>
      </div>
      <div class="pff-split-img">
        <?php if ($s1_url) : ?>
          <img src="<?php echo esc_url($s1_url); ?>" alt="<?php esc_attr_e('Formation femmes', 'araild'); ?>" class="pff-photo">
        <?php else : ?>
          <div class="pff-photo-placeholder">
            <div class="pff-placeholder-inner">
              <span style="font-size:3rem">📷</span>
              <p><?php esc_html_e('Photo section 1', 'araild'); ?></p>
              <small><?php esc_html_e('Ajoutez une photo depuis la page d\'édition', 'araild'); ?></small>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ===== BLOC 2 : Modules de formation ===== -->
    <div id="modules" style="margin-bottom:72px">
      <div class="section-head fade-up">
        <span class="eyebrow"><?php
          $ey2 = get_post_meta($pid, '_pff_b2_eyebrow', true);
          echo $ey2 ? esc_html($ey2) : esc_html__('Nos modules', 'araild');
        ?></span>
        <h2><?php
          $t2 = get_post_meta($pid, '_pff_b2_titre', true);
          echo $t2 ? esc_html($t2) : esc_html__('Formations disponibles pour les femmes', 'araild');
        ?></h2>
        <p><?php
          $st2 = get_post_meta($pid, '_pff_b2_sous_titre', true);
          echo $st2 ? esc_html($st2) : esc_html__('Des formations pratiques adaptées aux réalités locales.', 'araild');
        ?></p>
      </div>

      <?php
      $modules = [];
      for ($i = 1; $i <= 6; $i++) {
          $icone = get_post_meta($pid, "_pff_m{$i}_icone", true);
          $nom   = get_post_meta($pid, "_pff_m{$i}_nom", true);
          $desc  = get_post_meta($pid, "_pff_m{$i}_desc", true);
          $color = get_post_meta($pid, "_pff_m{$i}_color", true);
          $duree = get_post_meta($pid, "_pff_m{$i}_duree", true);
          $modules[] = [
              $icone ?: ['🧵','🍳','🌿','💄','🧴','🪡'][$i-1],
              $nom   ?: [
                  __('Couture & Confection', 'araild'),
                  __('Transformation alimentaire', 'araild'),
                  __('Maraîchage & Agriculture', 'araild'),
                  __('Coiffure & Esthétique', 'araild'),
                  __('Savonnerie & Cosmétique', 'araild'),
                  __('Artisanat & Tressage', 'araild'),
              ][$i-1],
              $desc  ?: [
                  __('Coupe, couture, broderie, confection de tenues traditionnelles et modernes, création d\'une petite activité de couture.', 'araild'),
                  __('Conservation des aliments, fabrication de beurre de karité, jus naturels, condiments et produits dérivés.', 'araild'),
                  __('Techniques de maraîchage biologique, conservation des légumes, commercialisation des produits.', 'araild'),
                  __('Coiffure naturelle, tresses africaines, soins capillaires, création d\'un salon de coiffure.', 'araild'),
                  __('Fabrication de savon artisanal, crèmes, huiles essentielles et produits naturels à commercialiser.', 'araild'),
                  __('Tressage de paniers, nattes, objets décoratifs et utilitaires en matières naturelles locales.', 'araild'),
              ][$i-1],
              $color ?: ['#6a1b9a','#e91e63','#2e7d32','#e65100','#0277bd','#558b2f'][$i-1],
              $duree ?: ['4 semaines','3 semaines','4 semaines','3 semaines','2 semaines','3 semaines'][$i-1],
          ];
      }
      ?>

      <div class="grid grid-3 fade-up">
        <?php foreach ($modules as $m) : ?>
          <div class="card value-card fade-up" style="border-top:4px solid <?php echo esc_attr($m[3]); ?>">
            <div class="v-icon"><?php echo esc_html($m[0]); ?></div>
            <h3><?php echo esc_html($m[1]); ?></h3>
            <p><?php echo esc_html($m[2]); ?></p>
            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <span class="badge" style="background:<?php echo esc_attr($m[3]); ?>22;color:<?php echo esc_attr($m[3]); ?>">📅 <?php echo esc_html($m[4]); ?></span>
              <span class="badge"><?php esc_html_e('Gratuit', 'araild'); ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ===== BLOC 3 : Témoignage + Photo ===== -->
    <div class="pff-split pff-split-reverse fade-up" style="margin-bottom:72px">
      <div class="pff-split-img">
        <?php if ($s2_url) : ?>
          <img src="<?php echo esc_url($s2_url); ?>" alt="<?php esc_attr_e('Témoignage', 'araild'); ?>" class="pff-photo">
        <?php else : ?>
          <div class="pff-photo-placeholder">
            <div class="pff-placeholder-inner">
              <span style="font-size:3rem">📷</span>
              <p><?php esc_html_e('Photo section 2', 'araild'); ?></p>
              <small><?php esc_html_e('Ajoutez une photo depuis la page d\'édition', 'araild'); ?></small>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="pff-split-text">
        <span class="eyebrow"><?php
          $ey3 = get_post_meta($pid, '_pff_b3_eyebrow', true);
          echo $ey3 ? esc_html($ey3) : esc_html__('Témoignages', 'araild');
        ?></span>
        <h2><?php
          $t3 = get_post_meta($pid, '_pff_b3_titre', true);
          echo $t3 ? esc_html($t3) : esc_html__('Ce que disent nos bénéficiaires', 'araild');
        ?></h2>
        <?php
        $temoignages = [];
        for ($i = 1; $i <= 3; $i++) {
            $cite  = get_post_meta($pid, "_pff_t{$i}_cite", true);
            $nom   = get_post_meta($pid, "_pff_t{$i}_nom", true);
            $lieu  = get_post_meta($pid, "_pff_t{$i}_lieu", true);
            if ($cite || $nom) {
                $temoignages[] = [$cite, $nom, $lieu];
            }
        }
        if (empty($temoignages)) {
            $temoignages = [
                [__('Grâce à ARAILD, j\'ai appris la couture et aujourd\'hui je gère mon propre atelier. Cette formation a changé ma vie et celle de ma famille.', 'araild'), 'Marie N., Bafoussam', __('Module Couture', 'araild')],
                [__('La formation en transformation alimentaire m\'a permis de lancer ma propre production de jus naturels. Je vends maintenant au marché local.', 'araild'), 'Cécile T., Dschang', __('Module Transformation', 'araild')],
            ];
        }
        ?>
        <div style="display:grid;gap:20px;margin-top:16px">
          <?php foreach ($temoignages as $t) : ?>
            <blockquote class="info-box" style="margin:0;padding:20px 24px;border-left:4px solid var(--primary)">
              <p style="font-style:italic;margin-bottom:12px">"<?php echo esc_html($t[0]); ?>"</p>
              <footer style="font-weight:600;color:var(--primary)"><?php echo esc_html($t[1]); ?> <span style="font-weight:400;color:var(--muted);font-size:.85rem">— <?php echo esc_html($t[2]); ?></span></footer>
            </blockquote>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ===== GALERIE PHOTOS ===== -->
    <?php
    $galerie_ids = $galerie ? array_filter(array_map('absint', explode(',', $galerie))) : [];
    ?>
    <div style="margin-bottom:72px">
      <div class="section-head fade-up">
        <span class="eyebrow"><?php esc_html_e('Galerie', 'araild'); ?></span>
        <h2><?php
          $tg = get_post_meta($pid, '_pff_galerie_titre', true);
          echo $tg ? esc_html($tg) : esc_html__('Photos de nos formations', 'araild');
        ?></h2>
      </div>

      <?php if (!empty($galerie_ids)) : ?>
        <div class="pff-galerie fade-up">
          <?php foreach ($galerie_ids as $gid) :
            $gurl = wp_get_attachment_image_url($gid, 'araild-card');
            $galt = get_post_meta($gid, '_wp_attachment_image_alt', true);
            if ($gurl) : ?>
              <div class="pff-galerie-item">
                <img src="<?php echo esc_url($gurl); ?>" alt="<?php echo esc_attr($galt ?: __('Formation ARAILD', 'araild')); ?>" loading="lazy">
              </div>
          <?php endif; endforeach; ?>
        </div>
      <?php else : ?>
        <div class="pff-galerie-placeholder fade-up">
          <?php for ($i = 1; $i <= 6; $i++) : ?>
            <div class="pff-photo-placeholder" style="height:200px">
              <div class="pff-placeholder-inner">
                <span style="font-size:2rem">📷</span>
                <small><?php echo esc_html(sprintf(__('Photo galerie %d', 'araild'), $i)); ?></small>
              </div>
            </div>
          <?php endfor; ?>
        </div>
        <p class="fade-up" style="text-align:center;color:var(--muted);margin-top:16px;font-size:.9rem">
          <?php esc_html_e('Ajoutez des photos de galerie depuis l\'édition de cette page → champ "Galerie photos".', 'araild'); ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- ===== BLOC 4 : Conditions d'accès ===== -->
    <div class="pff-split fade-up" style="margin-bottom:72px">
      <div class="pff-split-text">
        <span class="eyebrow"><?php
          $ey4 = get_post_meta($pid, '_pff_b4_eyebrow', true);
          echo $ey4 ? esc_html($ey4) : esc_html__('Conditions & accès', 'araild');
        ?></span>
        <h2><?php
          $t4 = get_post_meta($pid, '_pff_b4_titre', true);
          echo $t4 ? esc_html($t4) : esc_html__('Qui peut s\'inscrire ?', 'araild');
        ?></h2>
        <?php
        $conditions_raw = get_post_meta($pid, '_pff_conditions', true);
        $conditions = $conditions_raw ? array_filter(array_map('trim', explode("\n", $conditions_raw))) : [
            __('Femmes de 18 ans et plus', 'araild'),
            __('Résidant au Cameroun (toutes régions)', 'araild'),
            __('Aucun diplôme requis', 'araild'),
            __('Formation gratuite — aucun frais d\'inscription', 'araild'),
            __('Présentiel (Bafoussam, Yaoundé, Dschang, Bamenda) ou à distance', 'araild'),
            __('Matériel de base fourni pour certains modules', 'araild'),
        ];
        ?>
        <ul style="display:grid;gap:12px;margin-top:20px;list-style:none;padding:0">
          <?php foreach ($conditions as $c) : ?>
            <li style="display:flex;gap:10px;align-items:flex-start">
              <span style="color:var(--primary);font-size:1.1rem;flex-shrink:0">✅</span>
              <span><?php echo esc_html($c); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="pff-split-img">
        <?php if ($s3_url) : ?>
          <img src="<?php echo esc_url($s3_url); ?>" alt="<?php esc_attr_e('Conditions accès', 'araild'); ?>" class="pff-photo">
        <?php else : ?>
          <div class="pff-photo-placeholder">
            <div class="pff-placeholder-inner">
              <span style="font-size:3rem">📷</span>
              <p><?php esc_html_e('Photo section 3', 'araild'); ?></p>
              <small><?php esc_html_e('Ajoutez une photo depuis la page d\'édition', 'araild'); ?></small>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ===== FORMULAIRE D'INSCRIPTION ===== -->
    <div id="inscription" style="max-width:680px;margin:0 auto">
      <div class="section-head fade-up" style="margin-bottom:32px">
        <span class="eyebrow"><?php esc_html_e('Inscription', 'araild'); ?></span>
        <h2><?php
          $tinsc = get_post_meta($pid, '_pff_insc_titre', true);
          echo $tinsc ? esc_html($tinsc) : esc_html__('S\'inscrire à une formation', 'araild');
        ?></h2>
        <p><?php esc_html_e('Remplissez le formulaire — nous vous recontactons sous 48h.', 'araild'); ?></p>
      </div>

      <?php $st = isset($_GET['pff']) ? sanitize_key($_GET['pff']) : ''; ?>
      <?php if ($st === 'success') : ?><div class="form-notice success"><?php esc_html_e('Inscription reçue ! Nous vous recontacterons prochainement.', 'araild'); ?></div><?php endif; ?>
      <?php if ($st === 'error')   : ?><div class="form-notice error"><?php esc_html_e('Une erreur est survenue. Vérifiez vos informations.', 'araild'); ?></div><?php endif; ?>

      <form class="araild-form fade-up" method="post" action="">
        <input type="hidden" name="araild_form_action" value="pff_inscription">
        <?php wp_nonce_field('araild_pff_insc', 'araild_pff_nonce'); ?>
        <div class="form-row">
          <div><label for="pff_nom"><?php esc_html_e('Nom complet', 'araild'); ?> *</label><input type="text" id="pff_nom" name="nom" required></div>
          <div><label for="pff_age"><?php esc_html_e('Âge', 'araild'); ?></label><input type="number" id="pff_age" name="age" min="18" max="99"></div>
        </div>
        <div class="form-row">
          <div><label for="pff_tel"><?php esc_html_e('Téléphone / WhatsApp', 'araild'); ?> *</label><input type="tel" id="pff_tel" name="telephone" required></div>
          <div><label for="pff_email"><?php esc_html_e('Email (optionnel)', 'araild'); ?></label><input type="email" id="pff_email" name="email"></div>
        </div>
        <div><label for="pff_ville"><?php esc_html_e('Ville / Localité', 'araild'); ?> *</label><input type="text" id="pff_ville" name="ville" required></div>
        <div>
          <label for="pff_module"><?php esc_html_e('Module souhaité', 'araild'); ?> *</label>
          <select id="pff_module" name="module" required>
            <option value=""><?php esc_html_e('— Choisir un module —', 'araild'); ?></option>
            <?php foreach ($modules as $m) : ?>
              <option value="<?php echo esc_attr($m[1]); ?>"><?php echo esc_html($m[0] . ' ' . $m[1]); ?></option>
            <?php endforeach; ?>
            <option value="Autre"><?php esc_html_e('Autre', 'araild'); ?></option>
          </select>
        </div>
        <div>
          <label for="pff_format"><?php esc_html_e('Format préféré', 'araild'); ?></label>
          <select id="pff_format" name="format">
            <option><?php esc_html_e('En présentiel', 'araild'); ?></option>
            <option><?php esc_html_e('À distance (WhatsApp / Zoom)', 'araild'); ?></option>
            <option><?php esc_html_e('Indifférent', 'araild'); ?></option>
          </select>
        </div>
        <div><label for="pff_msg"><?php esc_html_e('Message (optionnel)', 'araild'); ?></label><textarea id="pff_msg" name="message" rows="3" placeholder="<?php esc_attr_e('Expérience, disponibilités, besoins spécifiques...', 'araild'); ?>"></textarea></div>
        <div><button type="submit" class="btn btn-primary btn-lg" style="width:100%"><?php esc_html_e('Envoyer ma demande d\'inscription', 'araild'); ?></button></div>
      </form>
    </div>

  </div>
</section>

<style>
/* ===== Styles spécifiques à cette page ===== */
.pff-hero-overlay{position:absolute;inset:0;background:linear-gradient(135deg,rgba(106,27,154,.8),rgba(233,30,99,.5));z-index:1}
.page-hero{position:relative}

.pff-split{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
.pff-split-reverse{direction:rtl}.pff-split-reverse>*{direction:ltr}
@media(max-width:768px){.pff-split,.pff-split-reverse{grid-template-columns:1fr}.pff-split-reverse{direction:ltr}}

.pff-photo{width:100%;border-radius:16px;object-fit:cover;height:400px;box-shadow:0 16px 48px rgba(0,0,0,.15)}

.pff-photo-placeholder{
  width:100%;height:400px;border-radius:16px;
  border:2px dashed var(--border);
  background:var(--surface);
  display:flex;align-items:center;justify-content:center;
}
.pff-placeholder-inner{text-align:center;color:var(--muted)}
.pff-placeholder-inner p{margin:8px 0 4px;font-weight:600}
.pff-placeholder-inner small{font-size:.8rem}

.pff-galerie,.pff-galerie-placeholder{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:16px;
}
@media(max-width:600px){.pff-galerie,.pff-galerie-placeholder{grid-template-columns:repeat(2,1fr)}}
.pff-galerie-item img{width:100%;height:200px;object-fit:cover;border-radius:12px;transition:transform .3s}
.pff-galerie-item img:hover{transform:scale(1.03)}
</style>

<?php get_footer(); ?>
