<?php
defined('ABSPATH') || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class('kic-imported-canvas'); ?>>
<?php wp_body_open(); ?>
<?php while (have_posts()) : the_post(); ?>
<main id="main-content" class="kic-site-<?php echo (int) get_post_meta(get_the_ID(), '_kic_import_id', true); ?>">
<?php the_content(); ?>
</main>
<?php endwhile; ?>
<?php wp_footer(); ?>
</body>
</html>
