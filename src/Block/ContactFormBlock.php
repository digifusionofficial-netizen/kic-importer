<?php

namespace KIC\Importer\Block;

final class ContactFormBlock
{
    public static function register(): void
    {
        wp_register_script('kic-importer-editor', plugins_url('assets/editor.js', KIC_IMPORTER_FILE), array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'), KIC_IMPORTER_VERSION, true);
        register_block_type('kic/contact-form', array(
            'api_version' => 2,
            'editor_script' => 'kic-importer-editor',
            'attributes' => array(
                'formId' => array('type' => 'string'),
                'fields' => array('type' => 'array', 'default' => array()),
                'submitText' => array('type' => 'string', 'default' => 'Submit'),
                'styleId' => array('type' => 'string'),
                'buttonStyleId' => array('type' => 'string'),
                'buttonClassName' => array('type' => 'string'),
                'className' => array('type' => 'string'),
            ),
            'supports' => array('color' => true, 'spacing' => array('padding', 'margin'), 'border' => true, 'typography' => true),
            'render_callback' => array(self::class, 'render'),
        ));
    }

    /** @param array<string,mixed> $attributes */
    public static function render(array $attributes): string
    {
        $formId = sanitize_html_class((string) ($attributes['formId'] ?? 'contact'));
        $styleId = sanitize_html_class((string) ($attributes['styleId'] ?? $formId));
        $sourceClass = preg_replace('/[^A-Za-z0-9_ -]/', '', (string) ($attributes['className'] ?? '')) ?? '';
        $wrapper = function_exists('get_block_wrapper_attributes') ? get_block_wrapper_attributes(array('class' => trim('kic-contact-form ' . $sourceClass), 'data-kic-style-id' => $styleId)) : 'class="' . esc_attr(trim('kic-contact-form ' . $sourceClass)) . '" data-kic-style-id="' . esc_attr($styleId) . '"';
        $html = '<form ' . $wrapper . ' data-form-id="' . esc_attr($formId) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="kic_submit_form"><input type="hidden" name="kic_form_id" value="' . esc_attr($formId) . '">';
        $html .= wp_nonce_field('kic_form_' . $formId, '_kic_form_nonce', true, false);
        foreach ((array) ($attributes['fields'] ?? array()) as $index => $field) {
            $name = sanitize_key((string) ($field['name'] ?? 'field_' . $index));
            $id = $formId . '-' . $name;
            $fieldStyleId = sanitize_html_class((string) ($field['styleId'] ?? $id . '-field'));
            $controlStyleId = sanitize_html_class((string) ($field['controlStyleId'] ?? $id . '-control'));
            $labelStyleId = sanitize_html_class((string) ($field['labelStyleId'] ?? $id . '-label'));
            $type = in_array(($field['type'] ?? ''), array('text', 'email', 'tel', 'url', 'number', 'textarea', 'select'), true) ? $field['type'] : 'text';
            $html .= '<div class="form-field kic-src-form-field kic-style-' . esc_attr($fieldStyleId) . '"><label class="kic-style-' . esc_attr($labelStyleId) . '" for="' . esc_attr($id) . '">' . esc_html((string) ($field['label'] ?? ucfirst($name))) . '</label>';
            if ($type === 'textarea') {
                $html .= '<textarea class="kic-style-' . esc_attr($controlStyleId) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . (!empty($field['required']) ? ' required' : '') . '></textarea>';
            } elseif ($type === 'select') {
                $html .= '<select class="kic-style-' . esc_attr($controlStyleId) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . (!empty($field['required']) ? ' required' : '') . '>';
                foreach ((array) ($field['options'] ?? array()) as $option) { $html .= '<option value="' . esc_attr((string) ($option['value'] ?? '')) . '">' . esc_html((string) ($option['label'] ?? '')) . '</option>'; }
                $html .= '</select>';
            } else {
                $html .= '<input class="kic-style-' . esc_attr($controlStyleId) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" type="' . esc_attr($type) . '"' . (!empty($field['required']) ? ' required' : '') . '>';
            }
            $html .= '</div>';
        }
        $buttonStyleId = sanitize_html_class((string) ($attributes['buttonStyleId'] ?? $formId . '-submit'));
        $buttonClass = preg_replace('/[^A-Za-z0-9_ -]/', '', (string) ($attributes['buttonClassName'] ?? '')) ?? '';
        $html .= '<button type="submit" class="wp-element-button ' . esc_attr($buttonClass) . ' kic-style-' . esc_attr($buttonStyleId) . '">' . esc_html((string) ($attributes['submitText'] ?? 'Submit')) . '</button></form>';
        return $html;
    }
}
