<?php

namespace KIC\Importer\Form;

final class FormHandler
{
    public static function handle(): void
    {
        $formId = isset($_POST['kic_form_id']) ? sanitize_key(wp_unslash($_POST['kic_form_id'])) : '';
        if ($formId === '' || !isset($_POST['_kic_form_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_kic_form_nonce'])), 'kic_form_' . $formId)) {
            wp_die(esc_html__('Invalid form submission.', 'kic-importer'), 403);
        }
        $manifest = get_option('kic_last_manifest', array());
        $definition = null;
        foreach ((array) ($manifest['forms'] ?? array()) as $form) {
            if (($form['id'] ?? '') === $formId) { $definition = $form; break; }
        }
        if (!$definition) { wp_die(esc_html__('Unknown form.', 'kic-importer'), 400); }
        $lines = array();
        foreach ($_POST as $key => $value) {
            if (in_array($key, array('action', 'kic_form_id', '_kic_form_nonce'), true) || !is_scalar($value)) { continue; }
            $lines[] = sanitize_text_field($key) . ': ' . sanitize_textarea_field(wp_unslash((string) $value));
        }
        $sent = wp_mail(get_option('admin_email'), sprintf('[%s] Website form submission', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)), implode("\n", $lines));
        $redirect = wp_get_referer() ?: home_url('/');
        wp_safe_redirect(add_query_arg('kic-form', $sent ? 'sent' : 'failed', $redirect));
        exit;
    }
}
