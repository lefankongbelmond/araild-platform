<?php
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="screen-reader-text" href="#main"><?php esc_html_e('Aller au contenu', 'araild'); ?></a>

<!-- Barre supérieure -->
<div class="topbar">
  <div class="container">
    <div class="tb-left">
      <?php if (araild_opt('tel')) : ?><a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', araild_opt('tel'))); ?>">📞 <?php araild_e('tel', '+237 650 70 83 30'); ?></a><?php endif; ?>
      <?php if (araild_opt('email')) : ?><a href="mailto:<?php echo esc_attr(araild_opt('email')); ?>">✉️ <?php araild_e('email', 'contact@araild.com'); ?></a><?php endif; ?>
    </div>
    <div class="tb-right"><?php araild_e('hero_slogan', 'ARAILD — Action pour la Recherche et l\'Appui aux Initiatives Locales de Développement'); ?></div>
  </div>
</div>

<!-- Header sticky -->
<header class="site-header" id="site-header">
  <div class="container">
    <div class="header-inner">
      <div class="site-branding">
        <?php if (has_custom_logo()) : the_custom_logo(); else : ?>
          <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
            <span class="logo-text">ARAILD</span>
            <span class="logo-sub"><?php esc_html_e('Initiatives Locales de Développement', 'araild'); ?></span>
          </a>
        <?php endif; ?>
      </div>

      <nav class="main-nav" aria-label="<?php esc_attr_e('Menu principal', 'araild'); ?>">
        <?php
        if (has_nav_menu('primary')) {
            wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'depth' => 2]);
        } else {
            echo '<ul><li><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Accueil', 'araild') . '</a></li></ul>';
        }
        ?>
      </nav>

      <div class="header-cta">
        <a class="btn btn-outline" href="<?php echo esc_url(home_url('/contact')); ?>"><?php esc_html_e('Contact', 'araild'); ?></a>
        <a class="btn btn-primary" href="<?php echo esc_url(home_url('/nous-soutenir')); ?>"><?php esc_html_e('Nous soutenir', 'araild'); ?></a>
        <button class="menu-toggle" id="menu-toggle" aria-label="<?php esc_attr_e('Ouvrir le menu', 'araild'); ?>" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </div>
</header>

<!-- Menu mobile -->
<div class="mm-overlay" id="mm-overlay"></div>
<aside class="mobile-menu" id="mobile-menu" aria-label="<?php esc_attr_e('Menu mobile', 'araild'); ?>">
  <button class="close-mm" id="close-mm" aria-label="<?php esc_attr_e('Fermer le menu', 'araild'); ?>">&times;</button>
  <?php
  if (has_nav_menu('primary')) {
      wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'depth' => 2]);
  }
  ?>
  <div style="margin-top:24px;display:grid;gap:10px">
    <a class="btn btn-outline" href="<?php echo esc_url(home_url('/contact')); ?>"><?php esc_html_e('Contact', 'araild'); ?></a>
    <a class="btn btn-primary" href="<?php echo esc_url(home_url('/nous-soutenir')); ?>"><?php esc_html_e('Nous soutenir', 'araild'); ?></a>
  </div>
</aside>

<main id="main">
