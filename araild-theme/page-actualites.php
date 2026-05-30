<?php
/* Template Name: Actualités */
if (!defined('ABSPATH')) exit;
get_header();
$paged = max(1, get_query_var('paged'), get_query_var('page'));
$q = new WP_Query(['post_type' => 'post', 'posts_per_page' => 9, 'paged' => $paged]);
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Actualités', 'araild'); ?></nav>
    <h1><?php esc_html_e('Actualités', 'araild'); ?></h1>
    <p><?php esc_html_e('Suivez nos activités, événements et nouvelles de terrain.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php if ($q->have_posts()) : ?>
      <div class="post-grid">
        <?php while ($q->have_posts()) : $q->the_post(); ?>
          <article class="post-card fade-up">
            <a href="<?php the_permalink(); ?>" class="pc-thumb"><?php if (has_post_thumbnail()) the_post_thumbnail('araild-card'); ?></a>
            <div class="pc-content">
              <div class="post-meta"><?php echo esc_html(get_the_date()); ?> · <?php the_category(', '); ?></div>
              <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
              <p style="color:var(--gray-600);font-size:.92rem"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 22)); ?></p>
            </div>
          </article>
        <?php endwhile; ?>
      </div>
      <div class="pagination"><?php echo paginate_links(['total' => $q->max_num_pages, 'type' => 'list']); ?></div>
    <?php else : ?>
      <p class="text-center"><?php esc_html_e('Aucune actualité pour le moment.', 'araild'); ?></p>
    <?php endif; wp_reset_postdata(); ?>
  </div>
</section>
<?php get_footer();
