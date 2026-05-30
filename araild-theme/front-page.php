<?php
/**
 * Page d'accueil ARAILD — entièrement éditable bloc par bloc.
 */
if (!defined('ABSPATH')) exit;
get_header();
?>

<!-- ============================================================
  BLOC 1 : HERO
  Modifiable : Personnaliser → ARAILD → Accueil → Hero
  (titre, slogan, description, image de fond, 2 CTA, 4 stats)
============================================================= -->
<section class="hero">
  <?php $bg = araild_opt('hero_bg'); if ($bg) : ?>
    <?php echo wp_get_attachment_image($bg, 'araild-hero', false, ['class' => 'hero-bg', 'alt' => '']); ?>
  <?php endif; ?>
  <div class="hero-overlay"></div>
  <div class="container">
    <div class="hero-content fade-up">
      <p class="hero-slogan"><?php araild_e('hero_slogan'); ?></p>
      <h1><?php araild_e('hero_titre', 'Ensemble pour un développement durable et inclusif'); ?></h1>
      <p class="hero-desc"><?php araild_e('hero_description'); ?></p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="<?php echo esc_url(araild_opt('cta1_link', '/projets')); ?>"><?php araild_e('cta1_label', 'Nos projets'); ?></a>
        <a class="btn btn-glass btn-lg" href="<?php echo esc_url(araild_opt('cta2_link', '/nous-soutenir')); ?>"><?php araild_e('cta2_label', 'Nous soutenir'); ?></a>
      </div>
      <div class="hero-stats">
        <?php for ($i = 1; $i <= 4; $i++) :
          $val = araild_opt("stat{$i}_value");
          $lbl = araild_opt("stat{$i}_label");
          if (!$val) continue; ?>
          <div class="hero-stat">
            <div class="num" data-count="<?php echo esc_attr($val); ?>"><?php echo esc_html($val); ?></div>
            <div class="lbl"><?php echo esc_html($lbl); ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</section>

<?php if (araild_show('axes')) : ?>
<!-- ============================================================
  BLOC 2 : AXES — Modifiable : Personnaliser → Accueil → Axes
  Données : CPT « Axes »
============================================================= -->
<section class="section">
  <div class="container">
    <div class="section-head fade-up">
      <span class="eyebrow"><?php esc_html_e('Nos domaines d\'action', 'araild'); ?></span>
      <h2><?php araild_e('title_axes', 'Nos axes d\'intervention'); ?></h2>
      <p><?php araild_e('intro_axes'); ?></p>
    </div>
    <div class="grid grid-4 axes-grid">
      <?php $q = araild_query('araild_axe', 4, 'menu_order', 'ASC');
      while ($q->have_posts()) : $q->the_post();
        $num = get_post_meta(get_the_ID(), '_araild_numero', true);
        $ico = get_post_meta(get_the_ID(), '_araild_icone', true);
        $resume = get_post_meta(get_the_ID(), '_araild_resume', true);
        $lien = get_post_meta(get_the_ID(), '_araild_lien', true) ?: get_permalink(); ?>
        <div class="card axe-card fade-up">
          <span class="axe-num"><?php echo esc_html($num); ?></span>
          <span class="axe-icon"><?php echo esc_html($ico); ?></span>
          <h3><?php the_title(); ?></h3>
          <p><?php echo esc_html($resume ?: wp_trim_words(get_the_excerpt(), 18)); ?></p>
          <a class="btn btn-outline" href="<?php echo esc_url($lien); ?>"><?php esc_html_e('En savoir plus', 'araild'); ?></a>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (araild_show('valeurs')) : ?>
<!-- BLOC 3 : VALEURS — Personnaliser → Accueil → Valeurs / CPT « Valeurs » -->
<section class="section" style="background:var(--gray-50)">
  <div class="container">
    <div class="section-head fade-up">
      <span class="eyebrow"><?php esc_html_e('Ce qui nous guide', 'araild'); ?></span>
      <h2><?php araild_e('title_valeurs', 'Nos valeurs'); ?></h2>
      <p><?php araild_e('intro_valeurs'); ?></p>
    </div>
    <div class="grid grid-3">
      <?php $q = araild_query('araild_valeur', -1, 'menu_order', 'ASC');
      while ($q->have_posts()) : $q->the_post();
        $ico = get_post_meta(get_the_ID(), '_araild_icone', true); ?>
        <div class="card value-card fade-up">
          <div class="v-icon"><?php echo esc_html($ico); ?></div>
          <h3><?php the_title(); ?></h3>
          <p><?php echo esc_html(wp_trim_words(get_the_content(), 22)); ?></p>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (araild_show('impact')) : ?>
<!-- BLOC 4 : IMPACT — Personnaliser → Accueil → Impact / CPT « Résultats » -->
<section class="section impact">
  <div class="container">
    <div class="section-head fade-up">
      <span class="eyebrow" style="color:var(--gold)"><?php esc_html_e('Nos réalisations', 'araild'); ?></span>
      <h2><?php araild_e('title_impact', 'Notre impact en chiffres'); ?></h2>
      <p><?php araild_e('intro_impact'); ?></p>
    </div>
    <div class="impact-grid">
      <?php $q = araild_query('araild_resultat', 8, 'menu_order', 'ASC');
      while ($q->have_posts()) : $q->the_post();
        $chiffre = get_post_meta(get_the_ID(), '_araild_chiffre', true);
        $domaine = get_post_meta(get_the_ID(), '_araild_domaine', true);
        $ico = get_post_meta(get_the_ID(), '_araild_icone', true);
        $label = trim(str_replace($chiffre, '', get_the_title())); ?>
        <div class="impact-card fade-up">
          <div class="ic-emoji"><?php echo esc_html($ico); ?></div>
          <div class="ic-num" data-count="<?php echo esc_attr($chiffre); ?>"><?php echo esc_html($chiffre); ?></div>
          <div class="ic-label"><?php echo esc_html($label); ?></div>
          <div class="ic-domain"><?php echo esc_html($domaine); ?></div>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (araild_show('projets')) : ?>
<!-- BLOC 5 : PROJETS VEDETTE — Personnaliser → Accueil → Projets / CPT « Projets » -->
<section class="section">
  <div class="container">
    <div class="section-head fade-up">
      <span class="eyebrow"><?php esc_html_e('Sur le terrain', 'araild'); ?></span>
      <h2><?php araild_e('title_projets', 'Projets en vedette'); ?></h2>
      <p><?php araild_e('intro_projets'); ?></p>
    </div>
    <div class="grid grid-3">
      <?php $q = araild_query('araild_projet', 3, 'date', 'DESC');
      while ($q->have_posts()) : $q->the_post();
        $statut = get_post_meta(get_the_ID(), '_araild_statut', true);
        $axe = get_post_meta(get_the_ID(), '_araild_axe', true); ?>
        <article class="card project-card fade-up">
          <div class="pc-img"><?php if (has_post_thumbnail()) the_post_thumbnail('araild-card'); ?></div>
          <div class="pc-body">
            <div class="pc-meta">
              <?php if ($statut) : ?><span class="badge badge-accent"><?php echo esc_html($statut); ?></span><?php endif; ?>
              <?php if ($axe) : ?><span class="badge"><?php echo esc_html($axe); ?></span><?php endif; ?>
            </div>
            <h3><?php the_title(); ?></h3>
            <p style="color:var(--gray-600);font-size:.92rem"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 20)); ?></p>
            <a href="<?php the_permalink(); ?>" style="font-family:var(--font-title);font-weight:600"><?php esc_html_e('Découvrir →', 'araild'); ?></a>
          </div>
        </article>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <div class="text-center" style="margin-top:36px"><a class="btn btn-primary" href="<?php echo esc_url(home_url('/projets')); ?>"><?php esc_html_e('Tous nos projets', 'araild'); ?></a></div>
  </div>
</section>
<?php endif; ?>

<?php if (araild_show('partenaires')) : ?>
<!-- BLOC 6 : PARTENAIRES — Personnaliser → Accueil → Partenaires / CPT « Partenaires » -->
<section class="section" style="background:var(--gray-50)">
  <div class="container">
    <div class="section-head fade-up">
      <span class="eyebrow"><?php esc_html_e('Ils nous accompagnent', 'araild'); ?></span>
      <h2><?php araild_e('title_partenaires', 'Nos partenaires'); ?></h2>
      <p><?php araild_e('intro_partenaires'); ?></p>
    </div>
    <div class="partners-grid">
      <?php $q = araild_query('araild_partenaire', -1, 'menu_order', 'ASC');
      while ($q->have_posts()) : $q->the_post();
        $url = get_post_meta(get_the_ID(), '_araild_url', true); ?>
        <div class="partner-card fade-up">
          <?php if (has_post_thumbnail()) {
            the_post_thumbnail('araild-thumb', ['alt' => get_the_title()]);
          } else {
            echo '<span class="p-name">' . esc_html(get_the_title()) . '</span>';
          } ?>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (araild_show('equipe')) : ?>
<!-- BLOC 7 : ÉQUIPE — Personnaliser → Accueil → Équipe / CPT « Membres » -->
<section class="section">
  <div class="container">
    <div class="section-head fade-up">
      <span class="eyebrow"><?php esc_html_e('À votre service', 'araild'); ?></span>
      <h2><?php araild_e('title_equipe', 'Notre équipe'); ?></h2>
      <p><?php araild_e('intro_equipe'); ?></p>
    </div>
    <div class="team-grid">
      <?php $q = araild_query('araild_membre', -1, 'menu_order', 'ASC');
      while ($q->have_posts()) : $q->the_post();
        $role = get_post_meta(get_the_ID(), '_araild_role', true); ?>
        <div class="card member-card fade-up">
          <div class="m-photo">
            <?php if (has_post_thumbnail()) the_post_thumbnail('araild-thumb');
            else echo '<span class="m-initials">' . esc_html(araild_initials(get_the_title())) . '</span>'; ?>
          </div>
          <div class="m-body">
            <h3><?php the_title(); ?></h3>
            <div class="m-role"><?php echo esc_html($role); ?></div>
          </div>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- BLOC 8 : CONTENU LIBRE — Éditeur Gutenberg de la page « Accueil » -->
<?php
while (have_posts()) : the_post();
  if (trim(get_the_content())) : ?>
    <section class="section"><div class="container"><div class="entry-content fade-up"><?php the_content(); ?></div></div></section>
  <?php endif;
endwhile;
?>

<!-- BLOC 9 : CTA FINAL → géré dans footer.php (Personnaliser → Footer & CTA) -->

<?php get_footer(); ?>
