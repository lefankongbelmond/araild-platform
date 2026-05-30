<?php
/* Template Name: Projets */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Projets', 'araild'); ?></nav>
    <h1><?php esc_html_e('Nos projets', 'araild'); ?></h1>
    <p><?php esc_html_e('Découvrez nos initiatives de terrain au service des communautés.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php
    $q = araild_query('araild_projet', -1, 'date', 'DESC');
    $axes = [];
    $items = [];
    while ($q->have_posts()) : $q->the_post();
      $axe = get_post_meta(get_the_ID(), '_araild_axe', true);
      if ($axe) $axes[$axe] = true;
      $items[] = get_the_ID();
    endwhile; wp_reset_postdata();
    ?>
    <div class="project-filters">
      <button class="filter-btn active" data-filter="all"><?php esc_html_e('Tous', 'araild'); ?></button>
      <?php foreach (array_keys($axes) as $axe) : ?>
        <button class="filter-btn" data-filter="<?php echo esc_attr(sanitize_title($axe)); ?>"><?php echo esc_html($axe); ?></button>
      <?php endforeach; ?>
    </div>
    <div class="grid grid-3">
      <?php $q2 = araild_query('araild_projet', -1, 'date', 'DESC');
      while ($q2->have_posts()) : $q2->the_post();
        $statut = get_post_meta(get_the_ID(), '_araild_statut', true);
        $axe = get_post_meta(get_the_ID(), '_araild_axe', true);
        $av = (int) get_post_meta(get_the_ID(), '_araild_avancement', true);
        $lieu = get_post_meta(get_the_ID(), '_araild_lieu', true); ?>
        <article class="card project-card fade-up" data-axe="<?php echo esc_attr(sanitize_title($axe)); ?>">
          <div class="pc-img"><?php if (has_post_thumbnail()) the_post_thumbnail('araild-card'); ?></div>
          <div class="pc-body">
            <div class="pc-meta">
              <?php if ($statut) : ?><span class="badge badge-accent"><?php echo esc_html($statut); ?></span><?php endif; ?>
              <?php if ($axe) : ?><span class="badge"><?php echo esc_html($axe); ?></span><?php endif; ?>
              <?php if ($lieu) : ?><span class="badge badge-blue">📍 <?php echo esc_html($lieu); ?></span><?php endif; ?>
            </div>
            <h3><?php the_title(); ?></h3>
            <p style="color:var(--gray-600);font-size:.92rem"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 22)); ?></p>
            <div style="margin-top:12px">
              <small style="font-family:var(--font-title);font-weight:600"><?php esc_html_e('Avancement', 'araild'); ?> : <?php echo esc_html($av); ?>%</small>
              <div class="progress-bar"><span data-progress="<?php echo esc_attr($av); ?>"></span></div>
            </div>
          </div>
        </article>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
<?php get_footer();
