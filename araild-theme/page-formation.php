<?php
/* Template Name: Formation en ligne */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Formation en ligne', 'araild'); ?></nav>
    <h1><?php esc_html_e('Formation en ligne', 'araild'); ?></h1>
    <p><?php esc_html_e('Renforcez vos capacités avec nos modules de formation à distance.', 'araild'); ?></p>
  </div>
</section>

<section class="section">
  <div class="container">

    <!-- Intro éditable depuis Pages → Formation en ligne -->
    <?php while (have_posts()) : the_post();
      if (trim(get_the_content())) : ?>
        <div class="entry-content fade-up" style="max-width:820px;margin:0 auto 48px"><?php the_content(); ?></div>
      <?php endif;
    endwhile; ?>

    <!-- Catégories de formation -->
    <div class="section-head fade-up">
      <span class="eyebrow"><?php esc_html_e('Nos thématiques', 'araild'); ?></span>
      <h2><?php esc_html_e('Modules disponibles', 'araild'); ?></h2>
      <p><?php esc_html_e('Des formations adaptées aux besoins des acteurs du développement local.', 'araild'); ?></p>
    </div>

    <div class="grid grid-3" style="margin-bottom:56px">
      <?php
      $formations = [
        ['🌾', 'Entrepreneuriat rural', 'Créer et gérer une activité génératrice de revenus, accéder au financement, structurer une coopérative.', '#6a1b9a'],
        ['🏥', 'Santé communautaire', 'Sensibilisation VIH/SIDA, santé maternelle, hygiène, nutrition, agents de santé communautaires.', '#e91e63'],
        ['🌍', 'Agroécologie', 'Production d\'engrais biologiques, greffage de plantes fruitières, pratiques durables et résilience climatique.', '#2e7d32'],
        ['⚖️', 'Gouvernance associative', 'Gestion et fiscalité des OBNL, comptabilité des associations, leadership et participation citoyenne.', '#2962ff'],
        ['📊', 'Gestion de projets', 'Planification, suivi-évaluation, rédaction de rapports, mobilisation de ressources.', '#f5c233'],
        ['💻', 'Numérique & communication', 'Outils numériques pour les OSC, communication digitale, réseaux sociaux au service des associations.', '#9c27b0'],
      ];
      foreach ($formations as $f) : ?>
        <div class="card value-card fade-up" style="border-top:4px solid <?php echo esc_attr($f[3]); ?>">
          <div class="v-icon"><?php echo esc_html($f[0]); ?></div>
          <h3><?php echo esc_html($f[1]); ?></h3>
          <p><?php echo esc_html($f[2]); ?></p>
          <div style="margin-top:16px">
            <span class="badge" style="background:<?php echo esc_attr($f[3]); ?>22;color:<?php echo esc_attr($f[3]); ?>"><?php esc_html_e('Module disponible', 'araild'); ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Comment accéder aux formations -->
    <div class="info-box fade-up" style="max-width:820px;margin:0 auto 48px">
      <h3 style="margin-bottom:16px"><?php esc_html_e('Comment accéder aux formations ?', 'araild'); ?></h3>
      <div class="grid grid-2" style="gap:20px">
        <div>
          <ul style="display:grid;gap:12px">
            <li>📧 <strong><?php esc_html_e('Par email :', 'araild'); ?></strong> <?php esc_html_e('Envoyez votre demande à', 'araild'); ?> <a href="mailto:<?php echo esc_attr(araild_opt('email', 'contact@araild.com')); ?>"><?php araild_e('email', 'contact@araild.com'); ?></a></li>
            <li>📞 <strong><?php esc_html_e('Par téléphone :', 'araild'); ?></strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', araild_opt('tel', '+237650708330'))); ?>"><?php araild_e('tel', '+237 650 70 83 30'); ?></a></li>
            <li>💬 <strong>WhatsApp :</strong> <a href="https://wa.me/<?php echo esc_attr(araild_opt('whatsapp', '237650708330')); ?>" target="_blank" rel="noopener"><?php araild_e('whatsapp', '237650708330'); ?></a></li>
          </ul>
        </div>
        <div>
          <ul style="display:grid;gap:12px">
            <li>🎯 <strong><?php esc_html_e('Formations en présentiel', 'araild'); ?></strong> — <?php esc_html_e('Bafoussam, Yaoundé, Bamenda, Dschang', 'araild'); ?></li>
            <li>🌐 <strong><?php esc_html_e('Formations à distance', 'araild'); ?></strong> — <?php esc_html_e('Via Zoom / WhatsApp', 'araild'); ?></li>
            <li>📅 <strong><?php esc_html_e('Sur demande groupée', 'araild'); ?></strong> — <?php esc_html_e('Pour associations & coopératives', 'araild'); ?></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Formulaire d'inscription -->
    <div style="max-width:640px;margin:0 auto">
      <div class="section-head fade-up" style="margin-bottom:32px">
        <h2><?php esc_html_e('S\'inscrire à une formation', 'araild'); ?></h2>
        <p><?php esc_html_e('Remplissez le formulaire et nous vous recontacterons dans les 48h.', 'araild'); ?></p>
      </div>
      <?php $status = isset($_GET['formation']) ? sanitize_key($_GET['formation']) : ''; ?>
      <?php if ($status === 'success') : ?><div class="form-notice success"><?php esc_html_e('Merci ! Votre inscription a bien été reçue. Nous vous contacterons prochainement.', 'araild'); ?></div><?php endif; ?>
      <?php if ($status === 'error') : ?><div class="form-notice error"><?php esc_html_e('Une erreur est survenue. Vérifiez vos informations et réessayez.', 'araild'); ?></div><?php endif; ?>

      <form class="araild-form fade-up" method="post" action="">
        <input type="hidden" name="araild_form_action" value="formation">
        <?php wp_nonce_field('araild_formation', 'araild_formation_nonce'); ?>
        <div class="form-row">
          <div><label for="f_nom"><?php esc_html_e('Nom complet', 'araild'); ?> *</label><input type="text" id="f_nom" name="nom" required></div>
          <div><label for="f_email"><?php esc_html_e('Email', 'araild'); ?> *</label><input type="email" id="f_email" name="email" required></div>
        </div>
        <div><label for="f_tel"><?php esc_html_e('Téléphone / WhatsApp', 'araild'); ?></label><input type="tel" id="f_tel" name="telephone"></div>
        <div>
          <label for="f_module"><?php esc_html_e('Module souhaité', 'araild'); ?></label>
          <select id="f_module" name="module">
            <option><?php esc_html_e('Entrepreneuriat rural', 'araild'); ?></option>
            <option><?php esc_html_e('Santé communautaire', 'araild'); ?></option>
            <option><?php esc_html_e('Agroécologie', 'araild'); ?></option>
            <option><?php esc_html_e('Gouvernance associative', 'araild'); ?></option>
            <option><?php esc_html_e('Gestion de projets', 'araild'); ?></option>
            <option><?php esc_html_e('Numérique & communication', 'araild'); ?></option>
            <option><?php esc_html_e('Autre', 'araild'); ?></option>
          </select>
        </div>
        <div>
          <label for="f_format"><?php esc_html_e('Format préféré', 'araild'); ?></label>
          <select id="f_format" name="format">
            <option><?php esc_html_e('En présentiel', 'araild'); ?></option>
            <option><?php esc_html_e('À distance (Zoom)', 'araild'); ?></option>
            <option><?php esc_html_e('WhatsApp', 'araild'); ?></option>
            <option><?php esc_html_e('Indifférent', 'araild'); ?></option>
          </select>
        </div>
        <div><label for="f_msg"><?php esc_html_e('Informations complémentaires', 'araild'); ?></label><textarea id="f_msg" name="message" placeholder="<?php esc_attr_e('Organisation, nombre de participants, disponibilités...', 'araild'); ?>"></textarea></div>
        <div><button type="submit" class="btn btn-primary btn-lg"><?php esc_html_e('Envoyer ma demande', 'araild'); ?></button></div>
      </form>
    </div>

  </div>
</section>

<?php get_footer();
