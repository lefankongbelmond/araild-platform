<?php
if (!defined('ABSPATH')) exit;
get_header();
?>
<div class="error-404">
  <div class="container">
    <div class="big">404</div>
    <h1><?php esc_html_e('Page introuvable', 'araild'); ?></h1>
    <p style="color:var(--gray-600);max-width:520px;margin:14px auto 28px"><?php esc_html_e('Désolé, la page que vous recherchez n\'existe pas ou a été déplacée.', 'araild'); ?></p>
    <div style="max-width:480px;margin:0 auto 24px"><?php get_search_form(); ?></div>
    <a class="btn btn-primary btn-lg" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Retour à l\'accueil', 'araild'); ?></a>
  </div>
</div>
<?php get_footer();
