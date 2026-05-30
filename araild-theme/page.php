<?php
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) : the_post(); ?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php the_title(); ?></nav>
    <h1><?php the_title(); ?></h1>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="entry-content fade-up"><?php the_content(); ?></div>
  </div>
</section>
<?php endwhile;
get_footer();
