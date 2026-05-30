<?php
/* Template Name: FAQ */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('FAQ', 'araild'); ?></nav>
    <h1><?php esc_html_e('Questions fréquentes', 'araild'); ?></h1>
    <p><?php esc_html_e('Tout ce que vous devez savoir sur l\'ARAILD.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container" style="max-width:820px">
    <?php $q = araild_query('araild_faq', -1, 'menu_order', 'ASC');
    if ($q->have_posts()) :
      while ($q->have_posts()) : $q->the_post();
        $rep = get_post_meta(get_the_ID(), '_araild_reponse', true);
        $rep = $rep ?: get_the_content(); ?>
        <div class="faq-item fade-up">
          <div class="faq-q"><span><?php the_title(); ?></span><span class="faq-ico">+</span></div>
          <div class="faq-a"><p><?php echo esc_html($rep); ?></p></div>
        </div>
      <?php endwhile; wp_reset_postdata();
    else : ?>
      <p class="text-center"><?php esc_html_e('Aucune question pour le moment.', 'araild'); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php get_footer();
