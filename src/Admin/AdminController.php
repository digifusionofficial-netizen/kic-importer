<?php

namespace KIC\Importer\Admin;

use KIC\Importer\Compatibility\CompatibilityManager;
use KIC\Importer\Import\ImportService;
use KIC\Importer\Import\RollbackManager;

final class AdminController
{
    public function register(): void
    {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_post_kic_validate', array($this, 'validate'));
        add_action('admin_post_kic_import', array($this, 'import'));
        add_action('admin_post_kic_rollback', array($this, 'rollback'));
    }

    public function menu(): void
    {
        add_management_page('KIC Importer', 'KIC Importer', 'manage_options', 'kic-importer', array($this, 'render'));
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) { return; }
        $status = (new CompatibilityManager())->inspect();
        $report = get_transient('kic_admin_report_' . get_current_user_id());
        delete_transient('kic_admin_report_' . get_current_user_id());
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('KIC-1.0 Importer', 'kic-importer'); ?></h1>
            <p><?php echo esc_html($status->message()); ?></p>
            <?php if (is_array($report)) : ?>
                <div class="notice <?php echo $report['status'] === 'pass' ? 'notice-success' : 'notice-error'; ?>"><p><strong><?php echo esc_html(strtoupper($report['status'])); ?></strong></p><pre><?php echo esc_html((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></div>
            <?php endif; ?>
            <h2><?php esc_html_e('Validate or import a KIC package', 'kic-importer'); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('kic_upload'); ?>
                <input type="file" name="kic_zip" accept=".zip,application/zip" required>
                <h3><?php esc_html_e('Placeholder replacements', 'kic-importer'); ?></h3>
                <p><?php esc_html_e('Validate first to see every placeholder declared by the package. Empty values are allowed only for draft import and will produce a visible warning.', 'kic-importer'); ?></p>
                <table class="form-table"><tbody>
                <?php foreach (array('PHONE' => 'Phone', 'EMAIL' => 'Email', 'ADDRESS' => 'Address', 'SERVICE_AREA' => 'Service area', 'BUSINESS_HOURS' => 'Business hours') as $token => $label) : ?>
                    <tr><th scope="row"><label for="kic-placeholder-<?php echo esc_attr(strtolower($token)); ?>"><?php echo esc_html($label); ?> <code>{{<?php echo esc_html($token); ?>}}</code></label></th><td><input class="regular-text" id="kic-placeholder-<?php echo esc_attr(strtolower($token)); ?>" name="placeholders[<?php echo esc_attr($token); ?>]" type="text"></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <p>
                    <button class="button" formaction="<?php echo esc_url(admin_url('admin-post.php?action=kic_validate')); ?>"><?php esc_html_e('Validate only', 'kic-importer'); ?></button>
                    <button class="button button-primary" formaction="<?php echo esc_url(admin_url('admin-post.php?action=kic_import')); ?>" <?php disabled(!$status->isSupported()); ?>><?php esc_html_e('Validate and import drafts', 'kic-importer'); ?></button>
                </p>
            </form>
            <h2><?php esc_html_e('Recent imports', 'kic-importer'); ?></h2>
            <table class="widefat striped"><thead><tr><th>ID</th><th>Status</th><th>Created</th><th>Pages</th><th>Action</th></tr></thead><tbody>
            <?php foreach ((array) get_option('kic_import_index', array()) as $id) : $row = get_option('kic_import_' . (int) $id); if (!is_array($row)) { continue; } ?>
                <tr><td><?php echo (int) $id; ?></td><td><?php echo esc_html((string) $row['status']); ?></td><td><?php echo esc_html((string) $row['created_at']); ?></td><td><?php echo count((array) ($row['pages'] ?? array())); ?></td><td><?php if ($row['status'] === 'success') : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('kic_rollback_' . (int) $id); ?><input type="hidden" name="action" value="kic_rollback"><input type="hidden" name="import_id" value="<?php echo (int) $id; ?>"><button class="button"><?php esc_html_e('Rollback', 'kic-importer'); ?></button></form><?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    public function validate(): void { $this->handle(false); }
    public function import(): void { $this->handle(true); }

    public function rollback(): void
    {
        $this->authorize();
        $id = isset($_POST['import_id']) ? absint($_POST['import_id']) : 0;
        check_admin_referer('kic_rollback_' . $id);
        try {
            (new RollbackManager())->rollback($id);
            $report = array('status' => 'pass', 'message' => 'Import rolled back.');
        } catch (\Throwable $error) {
            $report = array('status' => 'fail', 'errors' => array(array('message' => $error->getMessage())));
        }
        $this->redirect($report);
    }

    private function handle(bool $import): void
    {
        $this->authorize();
        check_admin_referer('kic_upload');
        if (empty($_FILES['kic_zip']['tmp_name']) || !is_uploaded_file($_FILES['kic_zip']['tmp_name'])) {
            $this->redirect(array('status' => 'fail', 'errors' => array(array('message' => 'No valid ZIP upload was received.'))));
        }
        try {
            $service = new ImportService();
            $values = isset($_POST['placeholders']) && is_array($_POST['placeholders']) ? wp_unslash($_POST['placeholders']) : array();
            $report = $import ? $service->import($_FILES['kic_zip']['tmp_name'], $values) : $service->validateZip($_FILES['kic_zip']['tmp_name']);
        } catch (\Throwable $error) {
            $report = array('status' => 'fail', 'errors' => array(array('message' => $error->getMessage())));
        }
        $this->redirect($report);
    }

    private function authorize(): void
    {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Insufficient permissions.', 'kic-importer'), 403); }
    }

    /** @param array<string,mixed> $report */
    private function redirect(array $report): void
    {
        set_transient('kic_admin_report_' . get_current_user_id(), $report, 120);
        wp_safe_redirect(admin_url('tools.php?page=kic-importer'));
        exit;
    }
}
