<?php
/* Template Name: Contact */
if (!defined('ABSPATH')) exit;
get_header();
$status = isset($_GET['contact']) ? sanitize_key($_GET['contact']) : '';
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'araild'); ?></a> / <?php esc_html_e('Contact', 'araild'); ?></nav>
    <h1><?php esc_html_e('Contactez-nous', 'araild'); ?></h1>
    <p><?php esc_html_e('Une question, un projet, un partenariat ? Écrivez-nous.', 'araild'); ?></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="contact-grid">
      <div class="fade-up">
        <h2><?php esc_html_e('Nos coordonnées', 'araild'); ?></h2>
        <ul class="contact-info" style="margin-top:24px">
          <?php if (araild_opt('adresse')) : ?><li><span class="ci-icon">📍</span><div><strong><?php esc_html_e('Adresse', 'araild'); ?></strong><br><?php araild_e('adresse'); ?></div></li><?php endif; ?>
          <?php if (araild_opt('tel')) : ?><li><span class="ci-icon">📞</span><div><strong><?php esc_html_e('Téléphone', 'araild'); ?></strong><br><a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', araild_opt('tel'))); ?>"><?php araild_e('tel'); ?></a></div></li><?php endif; ?>
          <?php if (araild_opt('email')) : ?><li><span class="ci-icon">✉️</span><div><strong><?php esc_html_e('Email', 'araild'); ?></strong><br><a href="mailto:<?php echo esc_attr(araild_opt('email')); ?>"><?php araild_e('email'); ?></a></div></li><?php endif; ?>
          <?php if (araild_opt('whatsapp')) : ?><li><span class="ci-icon">💬</span><div><strong>WhatsApp</strong><br><a href="https://wa.me/<?php echo esc_attr(araild_opt('whatsapp')); ?>" target="_blank" rel="noopener"><?php araild_e('whatsapp'); ?></a></div></li><?php endif; ?>
          <?php if (araild_opt('horaires')) : ?><li><span class="ci-icon">🕐</span><div><strong><?php esc_html_e('Horaires', 'araild'); ?></strong><br><?php araild_e('horaires'); ?></div></li><?php endif; ?>
          <?php if (araild_opt('antennes')) : ?><li><span class="ci-icon">🏢</span><div><strong><?php esc_html_e('Antennes', 'araild'); ?></strong><br><?php araild_e('antennes'); ?></div></li><?php endif; ?>
        </ul>
      </div>
      <div class="fade-up">
        <?php if ($status === 'success') : ?><div class="form-notice success"><?php esc_html_e('Merci ! Votre message a bien été envoyé.', 'araild'); ?></div><?php endif; ?>
        <?php if ($status === 'error') : ?><div class="form-notice error"><?php esc_html_e('Une erreur est survenue. Vérifiez vos informations.', 'araild'); ?></div><?php endif; ?>
        <form class="araild-form" method="post" action="">
          <input type="hidden" name="araild_form_action" value="contact">
          <?php wp_nonce_field('araild_contact', 'araild_contact_nonce'); ?>
          <div class="form-row">
            <div><label for="c_nom"><?php esc_html_e('Nom complet', 'araild'); ?> *</label><input type="text" id="c_nom" name="nom" required></div>
            <div><label for="c_email"><?php esc_html_e('Email', 'araild'); ?> *</label><input type="email" id="c_email" name="email" required></div>
          </div>
          <div><label for="c_sujet"><?php esc_html_e('Sujet', 'araild'); ?></label><input type="text" id="c_sujet" name="sujet"></div>
          <div><label for="c_msg"><?php esc_html_e('Message', 'araild'); ?> *</label><textarea id="c_msg" name="message" required></textarea></div>
          <div><button type="submit" class="btn btn-primary btn-lg"><?php esc_html_e('Envoyer le message', 'araild'); ?></button></div>
        </form>
      </div>
    </div>
  </div>
</section>
<?php get_footer();
