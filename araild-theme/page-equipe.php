<?php
/* Template Name: Notre équipe */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Notre équipe', 'araild'); ?></nav>
    <h1><?php esc_html_e('Notre équipe', 'araild'); ?></h1>
    <p><?php esc_html_e('Des femmes et des hommes engagés au service des communautés.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="team-grid">
      <?php $q = araild_query('araild_membre', -1, 'menu_order', 'ASC');
      while ($q->have_posts()) : $q->the_post();
        $role = get_post_meta(get_the_ID(), '_araild_role', true); ?>
        <div class="card member-card fade-up">
          <div class="m-photo">
            <?php if (has_post_thumbnail()) the_post_thumbnail('araild-thumb');
            else echo '<span class="m-initials">' . esc_html(araild_initials(get_the_title())) . '</span>'; ?>
          </div>
          <div class="m-body">
            <h3><?php the_title(); ?></h3>
            <div class="m-role"><?php echo esc_html($role); ?></div>
            <?php if (get_the_content()) : ?><p style="color:var(--gray-600);font-size:.85rem;margin-top:8px"><?php echo esc_html(wp_trim_words(get_the_content(), 16)); ?></p><?php endif; ?>
          </div>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
<?php get_footer();
