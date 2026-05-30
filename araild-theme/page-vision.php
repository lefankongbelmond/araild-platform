<?php
/* Template Name: Vision & Mission */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Vision & Mission', 'araild'); ?></nav>
    <h1><?php esc_html_e('Vision & Mission', 'araild'); ?></h1>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="grid grid-2">
      <div class="card fade-up" style="border-top:4px solid var(--primary)">
        <span class="badge"><?php esc_html_e('Vision', 'araild'); ?></span>
        <h2 style="margin:14px 0"><?php esc_html_e('Notre vision', 'araild'); ?></h2>
        <p>Un Cameroun où chaque communauté locale dispose des moyens, des compétences et des ressources nécessaires pour bâtir un développement durable, inclusif et autonome.</p>
      </div>
      <div class="card fade-up" style="border-top:4px solid var(--accent)">
        <span class="badge badge-accent"><?php esc_html_e('Mission', 'araild'); ?></span>
        <h2 style="margin:14px 0"><?php esc_html_e('Notre mission', 'araild'); ?></h2>
        <p>Accompagner et appuyer les initiatives locales de développement par la recherche-action, le renforcement des capacités et la mobilisation des communautés autour de solutions durables.</p>
      </div>
    </div>
  </div>
</section>
<section class="section" style="background:var(--gray-50)">
  <div class="container">
    <div class="section-head fade-up"><h2><?php esc_html_e('Nos objectifs', 'araild'); ?></h2></div>
    <div class="objectives-grid">
      <?php
      $objs = [
        ['01', 'Autonomiser économiquement', 'Renforcer les capacités économiques des populations, notamment les jeunes et les femmes.'],
        ['02', 'Améliorer l\'accès aux services sociaux', 'Faciliter l\'accès à la santé, à l\'éducation et à la protection des plus vulnérables.'],
        ['03', 'Promouvoir la résilience climatique', 'Accompagner la transition écologique et l\'adaptation aux changements climatiques.'],
        ['04', 'Renforcer la gouvernance', 'Soutenir la bonne gouvernance, la paix et la participation citoyenne.'],
      ];
      foreach ($objs as $o) : ?>
        <div class="objective-card fade-up">
          <div class="o-num"><?php echo esc_html($o[0]); ?></div>
          <h3 style="margin:8px 0"><?php echo esc_html($o[1]); ?></h3>
          <p style="color:var(--gray-600)"><?php echo esc_html($o[2]); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php get_footer();
