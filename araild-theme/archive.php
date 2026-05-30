<?php
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <h1><?php the_archive_title(); ?></h1>
    <?php the_archive_description('<p>', '</p>'); ?>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php if (have_posts()) : ?>
      <div class="post-grid">
        <?php while (have_posts()) : the_post(); ?>
          <article class="post-card fade-up">
            <a href="<?php the_permalink(); ?>" class="pc-thumb"><?php if (has_post_thumbnail()) the_post_thumbnail('araild-card'); ?></a>
            <div class="pc-content">
              <div class="post-meta"><?php echo esc_html(get_the_date()); ?></div>
              <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
              <p style="color:var(--gray-600);font-size:.92rem"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 20)); ?></p>
            </div>
          </article>
        <?php endwhile; ?>
      </div>
      <div class="pagination"><?php echo paginate_links(['type' => 'list']); ?></div>
    <?php else : ?>
      <p class="text-center"><?php esc_html_e('Aucun contenu pour le moment.', 'araild'); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php get_footer();
