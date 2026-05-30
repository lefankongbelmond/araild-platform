<?php
/* Template Name: Résultats */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Résultats', 'araild'); ?></nav>
    <h1><?php esc_html_e('Nos résultats', 'araild'); ?></h1>
    <p><?php esc_html_e('Des indicateurs concrets de notre impact sur le terrain.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php
    $q = araild_query('araild_resultat', -1, 'menu_order', 'ASC');
    $groups = [];
    $maxByDomain = [];
    while ($q->have_posts()) : $q->the_post();
      $dom = get_post_meta(get_the_ID(), '_araild_domaine', true) ?: 'Autres';
      $chiffre = (float) preg_replace('/[^0-9.]/', '', get_post_meta(get_the_ID(), '_araild_chiffre', true));
      $groups[$dom][] = [
        'chiffre' => get_post_meta(get_the_ID(), '_araild_chiffre', true),
        'num'     => $chiffre,
        'icone'   => get_post_meta(get_the_ID(), '_araild_icone', true),
        'label'   => trim(str_replace(get_post_meta(get_the_ID(), '_araild_chiffre', true), '', get_the_title())),
      ];
      $maxByDomain[$dom] = max($maxByDomain[$dom] ?? 0, $chiffre);
    endwhile; wp_reset_postdata();

    foreach ($groups as $domaine => $rows) : ?>
      <div class="result-domain fade-up">
        <h3><?php echo esc_html($domaine); ?></h3>
        <?php foreach ($rows as $r) :
          $pct = $maxByDomain[$domaine] > 0 ? round(($r['num'] / $maxByDomain[$domaine]) * 100) : 0; ?>
          <div class="result-row">
            <span class="rr-icon"><?php echo esc_html($r['icone']); ?></span>
            <span class="rr-num" data-count="<?php echo esc_attr($r['chiffre']); ?>"><?php echo esc_html($r['chiffre']); ?></span>
            <div style="flex:1">
              <span><?php echo esc_html($r['label']); ?></span>
              <div class="progress-bar"><span data-progress="<?php echo esc_attr($pct); ?>"></span></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php get_footer();
