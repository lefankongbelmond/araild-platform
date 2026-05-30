<?php
/* Template Name: Galerie */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Galerie', 'araild'); ?></nav>
    <h1><?php esc_html_e('Galerie', 'araild'); ?></h1>
    <p><?php esc_html_e('Images de nos actions sur le terrain.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="entry-content fade-up" style="max-width:none">
      <?php
      while (have_posts()) : the_post();
        if (trim(get_the_content())) { the_content(); }
        else { echo '<p class="text-center">' . esc_html__('Ajoutez des blocs Galerie à cette page depuis l\'éditeur WordPress.', 'araild') . '</p>'; }
      endwhile; ?>
    </div>
  </div>
</section>
<?php get_footer();
