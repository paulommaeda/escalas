<?php if (!defined('ABSPATH')) { exit; } ?>
<!doctype html>
<html class="ccs-app-document" <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#000A52"><meta name="robots" content="noindex,nofollow"><title>Escalas • CCShalom</title><?php wp_head(); ?></head>
<body class="ccs-standalone"><?php wp_body_open(); echo CCS::shell(); wp_footer(); ?></body>
</html>
