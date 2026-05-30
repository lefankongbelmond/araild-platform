<?php if (!defined('ABSPATH')) exit; ?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
  <label class="screen-reader-text" for="s"><?php esc_html_e('Rechercher :', 'araild'); ?></label>
  <input type="search" id="s" name="s" placeholder="<?php esc_attr_e('Rechercher…', 'araild'); ?>" value="<?php echo get_search_query(); ?>" required>
  <button type="submit"><?php esc_html_e('Rechercher', 'araild'); ?></button>
</form>
