<?php
/**
 * Plugin Name: KIC Importer
 * Description: Strict KIC-1.0 to Gutenberg/Kadence importer.
 * Version: 0.1.0-dev
 * Text Domain: kic-importer
 */

defined('ABSPATH') || exit;

define('KIC_IMPORTER_VERSION', '0.1.0-dev');
define('KIC_IMPORTER_FILE', __FILE__);
define('KIC_IMPORTER_DIR', plugin_dir_path(__FILE__));

require_once KIC_IMPORTER_DIR . 'src/Autoloader.php';

KIC\Importer\Autoloader::register();
KIC\Importer\Plugin::boot();
