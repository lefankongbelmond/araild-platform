<?php
/* Template Name: Documentation */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Documentation', 'araild'); ?></nav>
    <h1><?php esc_html_e('Documentation', 'araild'); ?></h1>
    <p><?php esc_html_e('Rapports, publications et ressources de l\'ARAILD.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php
    $q = araild_query('araild_document', -1, 'menu_order', 'ASC');
    $groups = [];
    while ($q->have_posts()) : $q->the_post();
      $cat = get_post_meta(get_the_ID(), '_araild_categorie', true) ?: 'Documents';
      $fid = get_post_meta(get_the_ID(), '_araild_fichier', true);
      $groups[$cat][] = ['title' => get_the_title(), 'url' => $fid ? wp_get_attachment_url($fid) : '', 'desc' => get_the_excerpt()];
    endwhile; wp_reset_postdata();

    foreach ($groups as $cat => $docs) : ?>
      <div class="result-domain fade-up">
        <h3><?php echo esc_html($cat); ?></h3>
        <div class="doc-list">
          <?php foreach ($docs as $d) : ?>
            <div class="doc-item">
              <span class="d-icon">📄</span>
              <div class="d-meta">
                <h4><?php echo esc_html($d['title']); ?></h4>
                <?php if ($d['desc']) : ?><small style="color:var(--gray-600)"><?php echo esc_html($d['desc']); ?></small><?php endif; ?>
              </div>
              <?php if ($d['url']) : ?>
                <a class="btn btn-outline" href="<?php echo esc_url($d['url']); ?>" download><?php esc_html_e('Télécharger', 'araild'); ?></a>
              <?php else : ?>
                <span class="badge"><?php esc_html_e('Bientôt', 'araild'); ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php get_footer();
