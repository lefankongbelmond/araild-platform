<?php
/* Template Name: Axe 2 */
if (!defined('ABSPATH')) exit;
get_header();
$axe = get_posts(['post_type' => 'araild_axe', 'numberposts' => 1, 'meta_key' => '_araild_numero', 'meta_value' => '2']);
$axe = $axe ? $axe[0] : null;
$titre = $axe ? get_the_title($axe) : __('Axe 2', 'araild');
$ico = $axe ? get_post_meta($axe->ID, '_araild_icone', true) : '';
$desc = $axe ? apply_filters('the_content', $axe->post_content) : '';
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <a href="<?php echo esc_url(home_url('/axes-dintervention')); ?>"><?php esc_html_e('Axes', 'araild'); ?></a> / <?php echo esc_html($titre); ?></nav>
    <h1><?php echo esc_html($ico . ' ' . $titre); ?></h1>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="entry-content fade-up">
      <?php echo wp_kses_post($desc); ?>
      <div style="margin-top:30px"><?php while (have_posts()) : the_post(); the_content(); endwhile; ?></div>
    </div>
    <div class="text-center" style="margin-top:40px">
      <a class="btn btn-primary" href="<?php echo esc_url(home_url('/projets')); ?>"><?php esc_html_e('Projets liés à cet axe', 'araild'); ?></a>
    </div>
  </div>
</section>
<?php get_footer();
