<?php
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) : the_post(); ?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php echo esc_html(get_post_type_object(get_post_type())->labels->singular_name ?? ''); ?></nav>
    <h1><?php the_title(); ?></h1>
    <?php if ('post' === get_post_type()) : ?><p class="post-meta" style="color:rgba(255,255,255,.85)"><?php echo esc_html(get_the_date()); ?> · <?php the_category(', '); ?></p><?php endif; ?>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php if (has_post_thumbnail()) : ?><div class="entry-content" style="margin-bottom:30px"><?php the_post_thumbnail('araild-hero'); ?></div><?php endif; ?>
    <div class="entry-content fade-up"><?php the_content(); ?></div>
    <div class="entry-content" style="margin-top:40px">
      <a class="btn btn-outline" href="<?php echo esc_url(home_url('/actualites')); ?>">← <?php esc_html_e('Retour', 'araild'); ?></a>
    </div>
    <?php if (comments_open() || get_comments_number()) : ?><div class="entry-content" style="margin-top:40px"><?php comments_template(); ?></div><?php endif; ?>
  </div>
</section>
<?php endwhile;
get_footer();
