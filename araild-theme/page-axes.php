<?php
/* Template Name: Axes d'intervention */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Axes d\'intervention', 'araild'); ?></nav>
    <h1><?php esc_html_e('Nos axes d\'intervention', 'araild'); ?></h1>
    <p><?php esc_html_e('Quatre domaines d\'action complémentaires pour un développement durable et inclusif.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="grid grid-2 axes-grid">
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
          <p><?php echo esc_html($resume); ?></p>
          <a class="btn btn-outline" href="<?php echo esc_url($lien); ?>"><?php esc_html_e('Voir le détail', 'araild'); ?></a>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
<?php get_footer();
