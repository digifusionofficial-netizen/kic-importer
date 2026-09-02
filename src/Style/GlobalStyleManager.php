<?php

namespace KIC\Importer\Style;

final class GlobalStyleManager
{
    /** @param array<string,mixed> $design */
    public function store(array $design, int $importId): bool
    {
        update_option('kic_style_options_backup_' . $importId, array(
            'tokens' => get_option('kic_global_tokens', null),
            'fallback_css' => get_option('kic_fallback_css', null),
            'style_report' => get_option('kic_last_style_report', null),
        ), false);
        $tokens = array(
            '--kic-color-primary' => $design['colors']['primary'] ?? '', '--kic-color-secondary' => $design['colors']['secondary'] ?? '',
            '--kic-color-accent' => $design['colors']['accent'] ?? '', '--kic-color-text' => $design['colors']['text'] ?? '',
            '--kic-color-heading' => $design['colors']['heading'] ?? '', '--kic-color-background' => $design['colors']['background'] ?? '',
            '--kic-color-surface' => $design['colors']['surface'] ?? '', '--kic-color-border' => $design['colors']['border'] ?? '',
            '--kic-font-heading' => $design['typography']['heading_font'] ?? '', '--kic-font-body' => $design['typography']['body_font'] ?? '',
            '--kic-container-width' => isset($design['layout']['container_width_px']) ? $design['layout']['container_width_px'] . 'px' : '',
            '--kic-content-width' => isset($design['layout']['content_width_px']) ? $design['layout']['content_width_px'] . 'px' : '',
            '--kic-section-space-desktop' => isset($design['spacing']['section_desktop_px']) ? $design['spacing']['section_desktop_px'] . 'px' : '',
            '--kic-section-space-tablet' => isset($design['spacing']['section_tablet_px']) ? $design['spacing']['section_tablet_px'] . 'px' : '',
            '--kic-section-space-mobile' => isset($design['spacing']['section_mobile_px']) ? $design['spacing']['section_mobile_px'] . 'px' : '',
        );
        update_option('kic_global_tokens', array_filter($tokens, static fn ($value): bool => $value !== ''), false);
        update_option('kic_import_tokens_' . $importId, array_filter($tokens, static fn ($value): bool => $value !== ''), false);
        if (!class_exists('WP_Theme_JSON_Resolver')) { return false; }
        $postId = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
        if (!$postId) { return false; }
        $post = get_post($postId);
        if (!$post) { return false; }
        update_option('kic_global_styles_backup_' . $importId, (string) $post->post_content, false);
        $data = json_decode((string) $post->post_content, true);
        if (!is_array($data)) { $data = array('version' => 3, 'isGlobalStyles' => true); }
        $data['settings']['color']['palette'] = array(
            array('slug' => 'kic-primary', 'name' => 'KIC Primary', 'color' => $design['colors']['primary']),
            array('slug' => 'kic-secondary', 'name' => 'KIC Secondary', 'color' => $design['colors']['secondary']),
            array('slug' => 'kic-accent', 'name' => 'KIC Accent', 'color' => $design['colors']['accent']),
            array('slug' => 'kic-surface', 'name' => 'KIC Surface', 'color' => $design['colors']['surface']),
        );
        $data['settings']['typography']['fontFamilies'] = array(
            array('slug' => 'kic-heading', 'name' => (string) $design['typography']['heading_font'], 'fontFamily' => (string) $design['typography']['heading_font']),
            array('slug' => 'kic-body', 'name' => (string) $design['typography']['body_font'], 'fontFamily' => (string) $design['typography']['body_font']),
        );
        $data['styles']['color'] = array('text' => (string) $design['colors']['text'], 'background' => (string) $design['colors']['background']);
        $data['styles']['typography'] = array('fontFamily' => (string) $design['typography']['body_font'], 'fontSize' => $design['typography']['base_font_size_px'] . 'px', 'lineHeight' => (string) $design['typography']['body_line_height']);
        $data['styles']['elements']['heading']['color']['text'] = (string) $design['colors']['heading'];
        $data['styles']['elements']['heading']['typography']['fontFamily'] = (string) $design['typography']['heading_font'];
        $updated = wp_update_post(wp_slash(array('ID' => $postId, 'post_content' => wp_json_encode($data))), true);
        return !is_wp_error($updated);
    }

    public static function enqueue(): void
    {
        $postId = self::currentPostId();
        if (!$postId) { return; }
        $importId = (int) get_post_meta($postId, '_kic_import_id', true);
        if (!$importId) { return; }
        $tokens = (array) get_option('kic_import_tokens_' . $importId, array());
        $css = (string) get_option('kic_import_css_' . $importId, '');
        if (!$tokens && $css === '') { return; }
        $body = '';
        foreach ($tokens as $name => $value) { if (preg_match('/^--kic-[a-z0-9-]+$/', $name) && !preg_match('/[{};<>]/', (string) $value)) { $body .= $name . ':' . $value . ';'; } }
        $handle = 'kic-imported-styles-' . $importId;
        wp_register_style($handle, false, array(), KIC_IMPORTER_VERSION);
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, '.kic-site-' . $importId . '{' . $body . '}' . $css);
    }

    private static function currentPostId(): int
    {
        if (function_exists('is_singular') && is_singular()) { return (int) get_queried_object_id(); }
        if (is_admin()) {
            if (isset($_GET['post'])) { return absint(wp_unslash($_GET['post'])); }
            global $post;
            if ($post instanceof \WP_Post) { return (int) $post->ID; }
        }
        return 0;
    }
}
