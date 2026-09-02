<?php

defined('WP_UNINSTALL_PLUGIN') || exit;
global $wpdb;

delete_option('kic_last_manifest');
delete_option('kic_pending_homepage_id');
delete_option('kic_next_import_id');
delete_option('kic_global_tokens');
delete_option('kic_fallback_css');
delete_option('kic_last_style_report');
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'kic_import_css_%' OR option_name LIKE 'kic_import_tokens_%' OR option_name LIKE 'kic_import_style_report_%'");
$index = (array) get_option('kic_import_index', array());
foreach ($index as $id) {
    delete_option('kic_import_' . (int) $id);
}
delete_option('kic_import_index');
