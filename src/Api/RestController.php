<?php

namespace KIC\Importer\Api;

use KIC\Importer\Compatibility\CompatibilityManager;
use KIC\Importer\Contract\KicContract;

final class RestController
{
    public function register(): void { add_action('rest_api_init', array($this, 'routes')); }

    public function routes(): void
    {
        register_rest_route('kic-importer/v1', '/status', array('methods' => 'GET', 'callback' => array($this, 'status'), 'permission_callback' => static fn (): bool => current_user_can('manage_options')));
        register_rest_route('kic-importer/v1', '/imports/(?P<id>\d+)', array('methods' => 'GET', 'callback' => array($this, 'importReport'), 'permission_callback' => static fn (): bool => current_user_can('manage_options')));
    }

    public function status(): \WP_REST_Response
    {
        $status = (new CompatibilityManager())->inspect();
        return new \WP_REST_Response(array('plugin_version' => KIC_IMPORTER_VERSION, 'contract_version' => KicContract::VERSION, 'contract_fingerprint' => KicContract::FINGERPRINT, 'wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION, 'kadence' => $status->toArray()));
    }

    public function importReport(\WP_REST_Request $request)
    {
        $record = get_option('kic_import_' . (int) $request['id']);
        return is_array($record) ? new \WP_REST_Response($record) : new \WP_Error('kic_not_found', 'Import report not found.', array('status' => 404));
    }
}
