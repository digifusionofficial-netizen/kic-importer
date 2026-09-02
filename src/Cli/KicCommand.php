<?php

namespace KIC\Importer\Cli;

use KIC\Importer\Compatibility\CompatibilityManager;
use KIC\Importer\Import\ImportService;

final class KicCommand
{
    public function status(): void { \WP_CLI::line((string) wp_json_encode((new CompatibilityManager())->inspect()->toArray(), JSON_PRETTY_PRINT)); }

    /** @param array<int,string> $args */
    public function validate(array $args): void
    {
        if (empty($args[0])) { \WP_CLI::error('Usage: wp kic validate <package.zip>'); }
        $report = (new ImportService())->validateZip($args[0]);
        \WP_CLI::line((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($report['status'] !== 'pass') { \WP_CLI::halt(1); }
    }

    /** @param array<int,string> $args */
    public function import(array $args): void
    {
        if (empty($args[0])) { \WP_CLI::error('Usage: wp kic import <package.zip>'); }
        $report = (new ImportService())->import($args[0]);
        \WP_CLI::line((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($report['status'] !== 'pass') { \WP_CLI::halt(1); }
    }
}
