<?php

namespace KIC\Importer\Import;

use RuntimeException;

final class RollbackManager
{
    public function rollback(int $importId): void
    {
        $record = get_option('kic_import_' . $importId);
        if (!is_array($record)) {
            throw new RuntimeException('Import record was not found.');
        }
        $restoredPages = array();
        $pageBackups = get_option('kic_page_backup_' . $importId, array());
        if (is_array($pageBackups)) {
            foreach ($pageBackups as $backup) {
                if (!empty($backup['existed']) && !empty($backup['post'])) {
                    $this->restorePostBackup($backup);
                    $restoredPages[] = (int) $backup['id'];
                }
            }
        }
        foreach (($record['pages'] ?? array()) as $id) {
            if (!in_array((int) $id, $restoredPages, true) && (int) get_post_meta((int) $id, '_kic_import_id', true) === $importId) { wp_delete_post((int) $id, true); }
        }
        delete_option('kic_page_backup_' . $importId);
        $patternBackups = get_option('kic_pattern_backup_' . $importId, array());
        if (is_array($patternBackups)) {
            foreach ($patternBackups as $backup) {
                if (!empty($backup['existed']) && !empty($backup['post'])) { $this->restorePostBackup($backup); }
                elseif (!empty($backup['id'])) { wp_delete_post((int) $backup['id'], true); }
            }
        }
        delete_option('kic_pattern_backup_' . $importId);
        delete_option('kic_asset_map_' . $importId);
        delete_option('kic_import_css_' . $importId);
        delete_option('kic_import_tokens_' . $importId);
        delete_option('kic_import_style_report_' . $importId);
        foreach (($record['media'] ?? array()) as $id) {
            if ((int) get_post_meta((int) $id, '_kic_import_id', true) === $importId) {
                wp_delete_attachment((int) $id, true);
            }
        }
        $backup = get_option('kic_global_styles_backup_' . $importId, null);
        if ($backup !== null && class_exists('WP_Theme_JSON_Resolver')) {
            $globalPostId = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
            if ($globalPostId) { wp_update_post(wp_slash(array('ID' => $globalPostId, 'post_content' => (string) $backup))); }
            delete_option('kic_global_styles_backup_' . $importId);
        }
        $styleOptions = get_option('kic_style_options_backup_' . $importId, null);
        if (is_array($styleOptions)) {
            foreach (array('tokens' => 'kic_global_tokens', 'fallback_css' => 'kic_fallback_css', 'style_report' => 'kic_last_style_report') as $key => $option) {
                if ($styleOptions[$key] === null) { delete_option($option); } else { update_option($option, $styleOptions[$key], false); }
            }
            delete_option('kic_style_options_backup_' . $importId);
        }
        $record['status'] = 'rolled_back';
        $record['rolled_back_at'] = gmdate('c');
        update_option('kic_import_' . $importId, $record, false);
    }

    /** @param array<string,mixed> $backup */
    private function restorePostBackup(array $backup): void
    {
        $id = (int) $backup['id'];
        wp_update_post(wp_slash($backup['post']));
        foreach (array_keys((array) get_post_meta($id)) as $key) { delete_post_meta($id, (string) $key); }
        foreach ((array) ($backup['meta'] ?? array()) as $key => $values) {
            foreach ((array) $values as $value) { add_post_meta($id, (string) $key, maybe_unserialize($value)); }
        }
    }
}
