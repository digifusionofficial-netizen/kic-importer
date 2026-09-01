<?php

namespace KIC\Importer;

use KIC\Importer\Compatibility\CompatibilityManager;

final class Plugin
{
    public static function boot(): void
    {
        add_action('admin_notices', array(self::class, 'renderCompatibilityNotice'));
    }

    public static function renderCompatibilityNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $status = (new CompatibilityManager())->inspect();
        if ($status->isSupported()) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html($status->message())
        );
    }
}
