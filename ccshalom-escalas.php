<?php
/**
 * Plugin Name: CCShalom — Escalas
 * Description: Eventos com departamentos, equipes, voluntários por função e integração Portal Push.
 * Version: 0.11.2
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: CCShalom
 * License: GPL-2.0-or-later
 * Text Domain: ccshalom-escalas
 * Update URI: https://github.com/paulommaeda/escalas
 */
if (!defined('ABSPATH')) { exit; }
define('CCS_VERSION', '0.11.2');
define('CCS_FILE', __FILE__);
define('CCS_DIR', plugin_dir_path(__FILE__));
require_once CCS_DIR . 'includes/class-ccs.php';
register_activation_hook(__FILE__, ['CCS', 'activate']);
CCS::boot();

