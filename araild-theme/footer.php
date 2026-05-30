<?php
if (!defined('ABSPATH')) exit;
?>
</main>

<?php if (araild_show('cta')) : ?>
<!-- Bannière CTA -->
<section class="section">
  <div class="container">
    <div class="cta-banner fade-up">
      <h2><?php araild_e('cta_titre', 'Rejoignez notre mission'); ?></h2>
      <p><?php araild_e('cta_soustitre', 'Ensemble, construisons un avenir durable et inclusif pour les communautés du Cameroun.'); ?></p>
      <a class="btn btn-glass btn-lg" href="<?php echo esc_url(home_url('/nous-soutenir')); ?>"><?php esc_html_e('Nous soutenir', 'araild'); ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <span class="logo-text" style="background:var(--gradient-brand);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;font-family:var(--font-title);font-weight:800">ARAILD</span>
        <p><?php araild_e('footer_desc', 'ARAILD est une ONG camerounaise qui œuvre pour le développement durable et l\'autonomisation des communautés locales.'); ?></p>
        <div class="social-links">
          <?php foreach (['facebook' => 'f', 'linkedin' => 'in', 'youtube' => '▶', 'twitter' => 'x', 'instagram' => '◉'] as $net => $ico) :
            $url = araild_opt($net);
            if ($url) : ?>
              <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr(ucfirst($net)); ?>"><?php echo esc_html($ico); ?></a>
          <?php endif; endforeach; ?>
        </div>
      </div>

      <div>
        <h4><?php esc_html_e('Liens rapides', 'araild'); ?></h4>
        <?php
        if (has_nav_menu('footer')) {
            wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'depth' => 1]);
        } else { ?>
          <ul>
            <li><a href="<?php echo esc_url(home_url('/a-propos')); ?>"><?php esc_html_e('À propos', 'araild'); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/projets')); ?>"><?php esc_html_e('Projets', 'araild'); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/actualites')); ?>"><?php esc_html_e('Actualités', 'araild'); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/documentation')); ?>"><?php esc_html_e('Documentation', 'araild'); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/faq')); ?>"><?php esc_html_e('FAQ', 'araild'); ?></a></li>
          </ul>
        <?php } ?>
      </div>

      <div>
        <h4><?php esc_html_e('Axes d\'intervention', 'araild'); ?></h4>
        <ul>
          <?php
          $axes = araild_query('araild_axe', 4, 'menu_order', 'ASC');
          if ($axes->have_posts()) :
            while ($axes->have_posts()) : $axes->the_post();
              $lien = get_post_meta(get_the_ID(), '_araild_lien', true) ?: get_permalink();
              echo '<li><a href="' . esc_url($lien) . '">' . esc_html(get_the_title()) . '</a></li>';
            endwhile; wp_reset_postdata();
          endif; ?>
        </ul>
      </div>

      <div>
        <h4><?php esc_html_e('Contact', 'araild'); ?></h4>
        <ul class="footer-contact">
          <?php if (araild_opt('adresse')) : ?><li>📍 <span><?php araild_e('adresse'); ?></span></li><?php endif; ?>
          <?php if (araild_opt('tel')) : ?><li>📞 <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', araild_opt('tel'))); ?>"><?php araild_e('tel'); ?></a></li><?php endif; ?>
          <?php if (araild_opt('email')) : ?><li>✉️ <a href="mailto:<?php echo esc_attr(araild_opt('email')); ?>"><?php araild_e('email'); ?></a></li><?php endif; ?>
          <?php if (araild_opt('horaires')) : ?><li>🕐 <span><?php araild_e('horaires'); ?></span></li><?php endif; ?>
        </ul>
        <?php if (araild_opt('antennes')) : ?>
          <p class="footer-antennes"><strong><?php esc_html_e('Antennes :', 'araild'); ?></strong> <?php araild_e('antennes'); ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="footer-bottom">
      &copy; <?php echo esc_html(date('Y')); ?> ARAILD — <?php esc_html_e('Tous droits réservés.', 'araild'); ?>
      <?php esc_html_e('ONG enregistrée au Cameroun depuis mai 2019.', 'araild'); ?>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
