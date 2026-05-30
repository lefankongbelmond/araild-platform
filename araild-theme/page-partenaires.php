<?php
/* Template Name: Partenaires */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Partenaires', 'araild'); ?></nav>
    <h1><?php esc_html_e('Nos partenaires', 'araild'); ?></h1>
    <p><?php esc_html_e('Institutions et organisations qui nous accompagnent.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php
    $labels = ['strategique' => 'Partenaires stratégiques', 'technique' => 'Partenaires techniques', 'financier' => 'Partenaires financiers'];
    $q = araild_query('araild_partenaire', -1, 'menu_order', 'ASC');
    $groups = [];
    while ($q->have_posts()) : $q->the_post();
      $niv = get_post_meta(get_the_ID(), '_araild_niveau', true) ?: 'strategique';
      $groups[$niv][] = ['title' => get_the_title(), 'thumb' => get_the_post_thumbnail(get_the_ID(), 'araild-thumb'), 'url' => get_post_meta(get_the_ID(), '_araild_url', true)];
    endwhile; wp_reset_postdata();

    foreach ($groups as $niv => $partners) : ?>
      <div class="result-domain fade-up">
        <h3><?php echo esc_html($labels[$niv] ?? ucfirst($niv)); ?></h3>
        <div class="partners-grid">
          <?php foreach ($partners as $p) : ?>
            <?php $tag = $p['url'] ? 'a' : 'div'; ?>
            <<?php echo $tag; ?> class="partner-card"<?php if ($p['url']) echo ' href="' . esc_url($p['url']) . '" target="_blank" rel="noopener"'; ?>>
              <?php echo $p['thumb'] ? wp_kses_post($p['thumb']) : '<span class="p-name">' . esc_html($p['title']) . '</span>'; ?>
            </<?php echo $tag; ?>>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php get_footer();
