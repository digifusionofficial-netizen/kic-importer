<?php

namespace KIC\Importer;

use KIC\Importer\Compatibility\CompatibilityManager;
use KIC\Importer\Admin\AdminController;
use KIC\Importer\Api\RestController;
use KIC\Importer\Cli\KicCommand;
use KIC\Importer\Block\ContactFormBlock;
use KIC\Importer\Form\FormHandler;
use KIC\Importer\Style\GlobalStyleManager;

final class Plugin
{
    public static function boot(): void
    {
        add_action('admin_notices', array(self::class, 'renderCompatibilityNotice'));
        add_action('plugins_loaded', array(self::class, 'loadTextDomain'));
        add_action('init', array(ContactFormBlock::class, 'register'));
        add_action('admin_post_kic_submit_form', array(FormHandler::class, 'handle'));
        add_action('admin_post_nopriv_kic_submit_form', array(FormHandler::class, 'handle'));
        add_action('wp_enqueue_scripts', array(GlobalStyleManager::class, 'enqueue'));
        add_action('enqueue_block_editor_assets', array(GlobalStyleManager::class, 'enqueue'));
        add_filter('theme_page_templates', array(self::class, 'pageTemplates'));
        add_filter('template_include', array(self::class, 'templateInclude'));
        (new AdminController())->register();
        (new RestController())->register();
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('kic', new KicCommand());
        }
    }

    /** @param array<string,string> $templates @return array<string,string> */
    public static function pageTemplates(array $templates): array
    {
        $templates['kic-canvas.php'] = __('KIC Imported Canvas', 'kic-importer');
        return $templates;
    }

    public static function templateInclude(string $template): string
    {
        if (is_singular('page') && get_page_template_slug(get_queried_object_id()) === 'kic-canvas.php') {
            return KIC_IMPORTER_DIR . 'templates/kic-canvas.php';
        }
        return $template;
    }

    public static function loadTextDomain(): void
    {
        load_plugin_textdomain('kic-importer', false, dirname(plugin_basename(KIC_IMPORTER_FILE)) . '/languages');
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
