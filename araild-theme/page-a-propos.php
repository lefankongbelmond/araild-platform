<?php
/* Template Name: À propos */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('À propos', 'araild'); ?></nav>
    <h1><?php esc_html_e('À propos d\'ARAILD', 'araild'); ?></h1>
    <p><?php esc_html_e('Action pour la Recherche et l\'Appui aux Initiatives Locales de Développement', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="about-grid">
      <div class="fade-up">
        <span class="eyebrow"><?php esc_html_e('Notre histoire', 'araild'); ?></span>
        <h2><?php esc_html_e('Une ONG née d\'un engagement local', 'araild'); ?></h2>
        <p>ARAILD a été <strong>créée en 2018</strong> et <strong>officiellement enregistrée en mai 2019</strong> au Cameroun. Notre organisation est née de la volonté d'un groupe de professionnels et de citoyens engagés de répondre concrètement aux défis du développement des communautés locales.</p>
        <p>Depuis, nous menons des actions de terrain dans les domaines de l'autonomisation économique, de la santé, de l'éducation, de la résilience climatique et de la gouvernance.</p>
        <div class="entry-content"><?php while (have_posts()) : the_post(); the_content(); endwhile; ?></div>
      </div>
      <div class="fade-up">
        <div class="info-box">
          <h3 style="margin-bottom:16px"><?php esc_html_e('En bref', 'araild'); ?></h3>
          <ul style="display:grid;gap:12px">
            <li>📅 <strong><?php esc_html_e('Fondation :', 'araild'); ?></strong> <?php araild_e('annee', '2018'); ?></li>
            <li>📝 <strong><?php esc_html_e('Enregistrement :', 'araild'); ?></strong> <?php esc_html_e('Mai 2019', 'araild'); ?></li>
            <li>📍 <strong><?php esc_html_e('Siège :', 'araild'); ?></strong> <?php araild_e('adresse'); ?></li>
            <li>🏢 <strong><?php esc_html_e('Antennes :', 'araild'); ?></strong> <?php araild_e('antennes'); ?></li>
            <li>📞 <strong><?php esc_html_e('Contact :', 'araild'); ?></strong> <?php araild_e('tel'); ?></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>
<?php get_footer();
