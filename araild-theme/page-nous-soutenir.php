<?php
/* Template Name: Nous soutenir */
if (!defined('ABSPATH')) exit;
get_header();
$status = isset($_GET['soutien']) ? sanitize_key($_GET['soutien']) : '';
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Nous soutenir', 'araild'); ?></nav>
    <h1><?php esc_html_e('Nous soutenir', 'araild'); ?></h1>
    <p><?php esc_html_e('Votre engagement transforme des vies. Rejoignez notre mission.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="section-head fade-up"><h2><?php esc_html_e('Pourquoi nous soutenir ?', 'araild'); ?></h2><p><?php esc_html_e('Chaque contribution renforce l\'impact de nos actions au plus près des communautés.', 'araild'); ?></p></div>
    <div class="grid grid-3">
      <?php $why = [
        ['🎯', 'Un impact mesurable', 'Des résultats concrets et documentés sur le terrain.'],
        ['🤝', 'Une gouvernance transparente', 'Rapports publics et redevabilité rigoureuse.'],
        ['🌍', 'Un ancrage local', 'Des solutions conçues avec et pour les communautés.'],
      ];
      foreach ($why as $w) : ?>
        <div class="card value-card fade-up"><div class="v-icon"><?php echo esc_html($w[0]); ?></div><h3><?php echo esc_html($w[1]); ?></h3><p><?php echo esc_html($w[2]); ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<section class="section" style="background:var(--gray-50)">
  <div class="container">
    <div class="section-head fade-up"><h2><?php esc_html_e('Modalités de soutien', 'araild'); ?></h2></div>
    <div class="grid grid-4">
      <?php foreach ([['💶', 'Faire un don'], ['🤝', 'Partenariat'], ['🙋', 'Bénévolat'], ['📣', 'Relayer nos actions']] as $m) : ?>
        <div class="card fade-up text-center"><div class="v-icon"><?php echo esc_html($m[0]); ?></div><h3><?php echo esc_html($m[1]); ?></h3></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<section class="section">
  <div class="container" style="max-width:720px">
    <div class="section-head fade-up"><h2><?php esc_html_e('Proposez votre soutien', 'araild'); ?></h2></div>
    <?php if ($status === 'success') : ?><div class="form-notice success"><?php esc_html_e('Merci ! Votre proposition a bien été envoyée.', 'araild'); ?></div><?php endif; ?>
    <?php if ($status === 'error') : ?><div class="form-notice error"><?php esc_html_e('Une erreur est survenue. Vérifiez vos informations.', 'araild'); ?></div><?php endif; ?>
    <form class="araild-form" method="post" action="">
      <input type="hidden" name="araild_form_action" value="soutien">
      <?php wp_nonce_field('araild_soutien', 'araild_soutien_nonce'); ?>
      <div class="form-row">
        <div><label for="s_nom"><?php esc_html_e('Nom complet', 'araild'); ?> *</label><input type="text" id="s_nom" name="nom" required></div>
        <div><label for="s_email"><?php esc_html_e('Email', 'araild'); ?> *</label><input type="email" id="s_email" name="email" required></div>
      </div>
      <div><label for="s_type"><?php esc_html_e('Type de soutien', 'araild'); ?></label>
        <select id="s_type" name="type_soutien">
          <option><?php esc_html_e('Don', 'araild'); ?></option>
          <option><?php esc_html_e('Partenariat', 'araild'); ?></option>
          <option><?php esc_html_e('Bénévolat', 'araild'); ?></option>
          <option><?php esc_html_e('Autre', 'araild'); ?></option>
        </select>
      </div>
      <div><label for="s_msg"><?php esc_html_e('Message', 'araild'); ?></label><textarea id="s_msg" name="message"></textarea></div>
      <div><button type="submit" class="btn btn-primary btn-lg"><?php esc_html_e('Envoyer ma proposition', 'araild'); ?></button></div>
    </form>
  </div>
</section>
<?php get_footer();
