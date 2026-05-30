<?php
/* Template Name: Nos valeurs */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Nos valeurs', 'araild'); ?></nav>
    <h1><?php esc_html_e('Nos valeurs', 'araild'); ?></h1>
    <p><?php esc_html_e('Les principes qui guident chacune de nos actions.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="grid grid-3">
      <?php $q = araild_query('araild_valeur', -1, 'menu_order', 'ASC');
      while ($q->have_posts()) : $q->the_post();
        $ico = get_post_meta(get_the_ID(), '_araild_icone', true);
        $col = get_post_meta(get_the_ID(), '_araild_couleur', true) ?: '#6a1b9a'; ?>
        <div class="card value-card fade-up" style="border-top:4px solid <?php echo esc_attr($col); ?>">
          <div class="v-icon"><?php echo esc_html($ico); ?></div>
          <h3><?php the_title(); ?></h3>
          <p><?php echo esc_html(get_the_content()); ?></p>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
<?php get_footer();
