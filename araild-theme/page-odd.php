<?php
/* Template Name: ODD */
if (!defined('ABSPATH')) exit;
get_header();
$odds = [
  1 => ['Pas de pauvreté', '#e5243b'], 2 => ['Faim « zéro »', '#dda63a'],
  3 => ['Bonne santé et bien-être', '#4c9f38'], 4 => ['Éducation de qualité', '#c5192d'],
  5 => ['Égalité entre les sexes', '#ff3a21'], 6 => ['Eau propre et assainissement', '#26bde2'],
  7 => ['Énergie propre', '#fcc30b'], 8 => ['Travail décent et croissance', '#a21942'],
  9 => ['Industrie et innovation', '#fd6925'], 10 => ['Inégalités réduites', '#dd1367'],
  11 => ['Villes durables', '#fd9d24'], 12 => ['Consommation responsable', '#bf8b2e'],
  13 => ['Lutte climatique', '#3f7e44'], 14 => ['Vie aquatique', '#0a97d9'],
  15 => ['Vie terrestre', '#56c02b'], 16 => ['Paix et justice', '#00689d'],
  17 => ['Partenariats', '#19486a'],
];
$mapping = [
  'Axe 1 — Autonomisation économique' => [1, 2, 8, 10],
  'Axe 2 — Santé, Éducation, Protection' => [3, 4, 5, 16],
  'Axe 3 — Résilience climatique' => [13, 15, 6, 12],
  'Axe 4 — Gouvernance & paix' => [16, 17, 10],
];
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('ODD', 'araild'); ?></nav>
    <h1><?php esc_html_e('Nos contributions aux ODD', 'araild'); ?></h1>
    <p><?php esc_html_e('Comment nos axes d\'intervention s\'alignent sur les Objectifs de Développement Durable.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php foreach ($mapping as $axe => $list) : ?>
      <div class="result-domain fade-up">
        <h3><?php echo esc_html($axe); ?></h3>
        <div class="odd-grid">
          <?php foreach ($list as $n) : if (!isset($odds[$n])) continue; ?>
            <div class="odd-card" style="background:<?php echo esc_attr($odds[$n][1]); ?>">
              <div class="odd-num"><?php esc_html_e('ODD', 'araild'); ?> <?php echo esc_html($n); ?></div>
              <div class="odd-title"><?php echo esc_html($odds[$n][0]); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php get_footer();
