<?php
/* Template Name: Bibliothèque */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Bibliothèque', 'araild'); ?></nav>
    <h1><?php esc_html_e('Bibliothèque', 'araild'); ?></h1>
    <p><?php esc_html_e('Ressources, guides et publications pour les acteurs du développement local.', 'araild'); ?></p>
  </div>
</section>

<section class="section">
  <div class="container">

    <!-- Intro éditable depuis Pages → Bibliothèque -->
    <?php while (have_posts()) : the_post();
      if (trim(get_the_content())) : ?>
        <div class="entry-content fade-up" style="max-width:820px;margin:0 auto 48px"><?php the_content(); ?></div>
      <?php endif;
    endwhile; ?>

    <!-- Barre de recherche documents -->
    <div style="max-width:500px;margin:0 auto 48px">
      <?php get_search_form(); ?>
    </div>

    <?php
    // Récupérer tous les documents groupés par catégorie
    $q = araild_query('araild_document', -1, 'menu_order', 'ASC');
    $groups = [];
    while ($q->have_posts()) : $q->the_post();
      $cat = get_post_meta(get_the_ID(), '_araild_categorie', true) ?: 'Autres';
      $fid = get_post_meta(get_the_ID(), '_araild_fichier', true);
      $groups[$cat][] = [
        'id'    => get_the_ID(),
        'title' => get_the_title(),
        'url'   => $fid ? wp_get_attachment_url($fid) : '',
        'desc'  => get_the_excerpt(),
      ];
    endwhile;
    wp_reset_postdata();
    ?>

    <?php if (!empty($groups)) : ?>

      <!-- Filtres par catégorie -->
      <div class="project-filters fade-up">
        <button class="filter-btn active" data-filter="all"><?php esc_html_e('Toutes les ressources', 'araild'); ?></button>
        <?php foreach (array_keys($groups) as $cat) : ?>
          <button class="filter-btn" data-filter="<?php echo esc_attr(sanitize_title($cat)); ?>"><?php echo esc_html($cat); ?></button>
        <?php endforeach; ?>
      </div>

      <?php foreach ($groups as $cat => $docs) : ?>
        <div class="result-domain fade-up" data-cat="<?php echo esc_attr(sanitize_title($cat)); ?>">
          <h3>
            <?php
            $icons = ['Rapports' => '📊', 'Statutaires' => '📋', 'Stratégie' => '🎯', 'Communication' => '📣', 'Guides' => '📖', 'Formulaires' => '📝', 'Bulletins' => '📰', 'Autres' => '📄'];
            echo esc_html($icons[$cat] ?? '📄') . ' ' . esc_html($cat);
            ?>
          </h3>
          <div class="doc-list">
            <?php foreach ($docs as $d) : ?>
              <div class="doc-item">
                <span class="d-icon">📄</span>
                <div class="d-meta">
                  <h4><?php echo esc_html($d['title']); ?></h4>
                  <?php if ($d['desc']) : ?><small style="color:var(--muted)"><?php echo esc_html($d['desc']); ?></small><?php endif; ?>
                </div>
                <?php if ($d['url']) : ?>
                  <a class="btn btn-outline" href="<?php echo esc_url($d['url']); ?>" download><?php esc_html_e('Télécharger', 'araild'); ?></a>
                <?php else : ?>
                  <span class="badge"><?php esc_html_e('Bientôt disponible', 'araild'); ?></span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php else : ?>

      <!-- Ressources par défaut si aucun document CPT n'est publié -->
      <div class="section-head fade-up">
        <span class="eyebrow"><?php esc_html_e('Ressources', 'araild'); ?></span>
        <h2><?php esc_html_e('Nos publications', 'araild'); ?></h2>
        <p><?php esc_html_e('Ajoutez des documents depuis le menu Documents dans l\'administration WordPress.', 'araild'); ?></p>
      </div>

      <div class="grid grid-3 fade-up">
        <?php
        $ressources = [
          ['📊', 'Rapports d\'activités', 'Rapports annuels et bilans de nos actions sur le terrain.'],
          ['📋', 'Documents officiels', 'Statuts, récépissé d\'enregistrement et textes réglementaires.'],
          ['📖', 'Guides pratiques', 'Manuels de formation, guides thématiques pour les acteurs de terrain.'],
          ['📣', 'Publications', 'Bulletins d\'information, communiqués et brochures de présentation.'],
          ['🎯', 'Plans stratégiques', 'Documents de planification et de stratégie institutionnelle.'],
          ['📝', 'Formulaires', 'Formulaires de partenariat, de bénévolat et de demande de formation.'],
        ];
        foreach ($ressources as $r) : ?>
          <div class="card value-card fade-up">
            <div class="v-icon"><?php echo esc_html($r[0]); ?></div>
            <h3><?php echo esc_html($r[1]); ?></h3>
            <p><?php echo esc_html($r[2]); ?></p>
            <div style="margin-top:14px">
              <a class="btn btn-outline" href="<?php echo esc_url(home_url('/documentation')); ?>"><?php esc_html_e('Voir les documents', 'araild'); ?></a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

    <!-- Demander un document -->
    <div class="info-box fade-up" style="max-width:820px;margin:56px auto 0;text-align:center">
      <h3 style="margin-bottom:12px"><?php esc_html_e('Vous ne trouvez pas ce que vous cherchez ?', 'araild'); ?></h3>
      <p style="color:var(--muted);margin-bottom:20px"><?php esc_html_e('Contactez-nous pour demander un document spécifique ou signaler une ressource manquante.', 'araild'); ?></p>
      <a class="btn btn-primary" href="<?php echo esc_url(home_url('/contact')); ?>"><?php esc_html_e('Nous contacter', 'araild'); ?></a>
      <a class="btn btn-outline" href="<?php echo esc_url(home_url('/documentation')); ?>" style="margin-left:10px"><?php esc_html_e('Voir toute la documentation', 'araild'); ?></a>
    </div>

  </div>
</section>

<script>
(function(){
  var filterBtns = document.querySelectorAll('.project-filters .filter-btn');
  filterBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      filterBtns.forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      var f = btn.getAttribute('data-filter');
      document.querySelectorAll('.result-domain[data-cat]').forEach(function(d){
        d.style.display = (f === 'all' || d.getAttribute('data-cat') === f) ? '' : 'none';
      });
    });
  });
})();
</script>

<?php get_footer();
