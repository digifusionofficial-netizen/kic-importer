<?php
/**
 * Plugin Name: KIC Importer
 * Description: Strict KIC-1.0 to Gutenberg/Kadence importer.
 * Version: 1.4.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Text Domain: kic-importer
 */

defined('ABSPATH') || exit;

define('KIC_IMPORTER_VERSION', '1.4.0');
define('KIC_IMPORTER_FILE', __FILE__);
define('KIC_IMPORTER_DIR', plugin_dir_path(__FILE__));

require_once KIC_IMPORTER_DIR . 'src/Autoloader.php';

KIC\Importer\Autoloader::register();
KIC\Importer\Plugin::boot();
