<?php

namespace KIC\Importer\Import;

use KIC\Importer\Adapter\KadenceCoreAdapter;
use KIC\Importer\Compatibility\CompatibilityManager;
use KIC\Importer\Package\SecureZipExtractor;
use KIC\Importer\Schema\SiteSchemaBuilder;
use KIC\Importer\Validation\PackageValidator;
use RuntimeException;
use KIC\Importer\Style\FallbackStyleCompiler;
use KIC\Importer\Style\GlobalStyleManager;
use KIC\Importer\Style\CombinedCssCompiler;

final class ImportService
{
    /** @return array<string,mixed> */
    public function validateZip(string $zipPath): array
    {
        $temp = $this->tempDirectory();
        try {
            (new SecureZipExtractor())->extract($zipPath, $temp);
            $validator = new PackageValidator();
            $report = $validator->validate($temp)->toArray();
            $manifest = $validator->manifest();
            $report['placeholders'] = is_array($manifest) ? (array) ($manifest['placeholders'] ?? array()) : array();
            return $report;
        } catch (\Throwable $error) {
            return array('status' => 'fail', 'errors' => array(array('code' => 'archive_error', 'message' => $error->getMessage())));
        } finally {
            $this->removeDirectory($temp);
        }
    }

    /** @return array<string,mixed> */
    public function import(string $zipPath, array $placeholderValues = array()): array
    {
        $compatibility = (new CompatibilityManager())->inspect();
        if (!$compatibility->isSupported()) {
            throw new RuntimeException($compatibility->message());
        }
        $temp = $this->tempDirectory();
        $createdPages = array();
        $createdMedia = array();
        $pageBackups = array();
        $logger = new ImportLogger();
        try {
            (new SecureZipExtractor())->extract($zipPath, $temp);
            $validator = new PackageValidator();
            $validation = $validator->validate($temp);
            if (!$validation->passed()) {
                return $validation->toArray();
            }
            $manifest = $validator->manifest();
            if (!$manifest) {
                throw new RuntimeException('Validated manifest is unavailable.');
            }
            $schema = (new SiteSchemaBuilder())->build($temp, $manifest);
            $adapter = new KadenceCoreAdapter();
            $assetMap = $this->importMedia($temp, $manifest, $createdMedia, $logger);
            $assetUrls = array_map(static fn (array $item): string => $item['url'], $assetMap);
            $importId = $logger->id();
            $siteScope = 'kic-site-' . $importId;
            $placeholderValues = $this->sanitizePlaceholderValues($placeholderValues, (array) ($manifest['placeholders'] ?? array()));
            $adapter->configure($siteScope, $schema, $placeholderValues);
            update_option('kic_asset_map_' . $importId, $assetMap, false);
            $patterns = $this->createGlobalPatterns($schema, $adapter, $importId, $assetUrls, $manifest);
            $pageMap = array();
            foreach ($schema->pages() as $page) {
                $definition = $page['definition'];
                $sourceFile = (string) $definition['file'];
                $existing = get_posts(array('post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_kic_source_file', 'meta_value' => $sourceFile, 'numberposts' => 1));
                $existingId = $existing ? (int) $existing[0]->ID : 0;
                $pageBackups[$sourceFile] = $this->postBackup($existingId);
                $body = $this->rewriteAssetUrls($adapter->renderPage($page, $schema), $sourceFile, $assetUrls);
                $body = $this->rewriteInternalLinks($body, $manifest);
                (new AssetUrlRewriter())->assertNoLocalAssetPaths($body, $sourceFile, $assetUrls, (string) ($definition['slug'] ?? $sourceFile));
                $content = '<!-- wp:block {"ref":' . (int) $patterns['header'] . '} /-->' . "\n" . $body . "\n" . '<!-- wp:block {"ref":' . (int) $patterns['footer'] . '} /-->';
                $postarr = array(
                    'ID' => $existingId,
                    'post_type' => 'page',
                    'post_status' => 'draft',
                    'post_title' => sanitize_text_field((string) $definition['title']),
                    'post_name' => $definition['slug'] === '/' ? 'home' : sanitize_title(trim((string) $definition['slug'], '/')),
                    'post_content' => $content,
                    'menu_order' => (int) ($definition['menu_order'] ?? 0),
                    'meta_input' => array(
                        '_kic_contract_version' => 'KIC-1.0',
                        '_kic_source_file' => $sourceFile,
                        '_kic_source_hash' => hash('sha256', (string) file_get_contents($temp . '/' . $sourceFile)),
                        '_kic_seo_title' => (string) $definition['seo']['title'],
                        '_kic_seo_description' => (string) $definition['seo']['description'],
                        '_wp_page_template' => 'kic-canvas.php',
                    ),
                );
                $id = wp_insert_post(wp_slash($postarr), true);
                if (is_wp_error($id)) {
                    throw new RuntimeException($id->get_error_message());
                }
                $createdPages[] = (int) $id;
                $pageMap[(string) $definition['slug']] = (int) $id;
                $this->applySeo((int) $id, $definition['seo'], $assetUrls);
                $logger->add('info', 'Page imported as draft.', array('source' => $sourceFile, 'post_id' => $id));
            }
            update_option('kic_page_backup_' . $importId, $pageBackups, false);
            foreach ($createdPages as $id) {
                update_post_meta($id, '_kic_import_id', $importId);
            }
            foreach ($createdMedia as $id) {
                update_post_meta($id, '_kic_import_id', $importId);
            }
            $this->createMenus($manifest, $pageMap);
            if (isset($pageMap['/'])) {
                update_option('kic_pending_homepage_id', $pageMap['/'], false);
            }
            update_option('kic_last_manifest', $manifest, false);
            $globalStylesMapped = (new GlobalStyleManager())->store((array) $manifest['design'], $importId);
            $mapperFallbackCss = $this->rewriteFallbackAssetUrls((new FallbackStyleCompiler())->compile($adapter->mappingFallbacks(), $siteScope), $assetUrls);
            $sourceCss = array();
            foreach (array('assets/css/global.css', 'assets/css/components.css', 'assets/css/responsive.css') as $cssFile) {
                $raw = (string) file_get_contents($temp . '/' . $cssFile);
                $sourceCss[$cssFile] = (new AssetUrlRewriter())->rewrite($raw, $cssFile, $assetUrls, 'stylesheet:' . $cssFile);
            }
            $combined = (new CombinedCssCompiler())->compile($sourceCss, $siteScope);
            $combined['css'] .= "\n" . $mapperFallbackCss;
            update_option('kic_import_css_' . $importId, $combined['css'], false);
            update_option('kic_fallback_css', $combined['css'], false); // Legacy diagnostics only; enqueue is per import.
            $mappingReport = array(
                'native' => $adapter->nativeMappings(),
                'fallback' => array_merge($schema->stylesheet()->unsupported(), $adapter->mappingFallbacks()),
                'combined_css' => array('files' => $combined['files'], 'bytes' => $combined['bytes'], 'rules' => $combined['rules'], 'sanitized' => $combined['sanitized'], 'scope' => '.' . $siteScope, 'preserves_unsupported_properties' => true),
            );
            if (!$globalStylesMapped) { $mappingReport['fallback'][] = array('scope' => 'global-styles', 'reason' => 'The active theme has no writable WordPress user global-styles record; scoped KIC tokens remain active as the fallback.'); }
            update_option('kic_import_style_report_' . $importId, $mappingReport, false);
            update_option('kic_last_style_report', $mappingReport, false);
            foreach ($mappingReport['fallback'] as $item) { $logger->add('warning', 'CSS mapping fallback or unsupported rule (preserved by the scoped stylesheet).', $item); }
            $unresolved = array_values(array_diff(array_keys((array) ($manifest['placeholders'] ?? array())), array_keys(array_filter($placeholderValues, static fn (string $value): bool => $value !== ''))));
            foreach ($unresolved as $name) { $logger->add('warning', 'Unresolved placeholder retained in draft.', array('placeholder' => $name)); }
            $logger->save('success', $createdPages, $createdMedia);
            return array('status' => 'pass', 'import_id' => $importId, 'pages' => $createdPages, 'adapter' => $adapter->name(), 'global_styles_mapped' => $globalStylesMapped, 'style_report' => $mappingReport, 'native_mapping_count' => count($mappingReport['native']), 'fallback_rule_count' => count($mappingReport['fallback']), 'unresolved_placeholders' => $unresolved, 'asset_map' => $assetMap, 'message' => $unresolved ? 'Import completed in draft status with unresolved placeholder warnings.' : 'Import completed in draft status.');
        } catch (\Throwable $error) {
            $this->restorePageBackups($pageBackups, $createdPages);
            $this->restorePatternBackups($logger->id());
            foreach ($createdMedia as $mediaId) { wp_delete_attachment((int) $mediaId, true); }
            delete_option('kic_asset_map_' . $logger->id());
            delete_option('kic_import_css_' . $logger->id());
            delete_option('kic_import_tokens_' . $logger->id());
            delete_option('kic_import_style_report_' . $logger->id());
            $logger->add('error', $error->getMessage());
            $logger->save('failed', array(), array());
            throw $error;
        } finally {
            $this->removeDirectory($temp);
        }
    }

    private function tempDirectory(): string
    {
        $base = trailingslashit(get_temp_dir()) . 'kic-' . wp_generate_uuid4();
        if (!wp_mkdir_p($base)) {
            throw new RuntimeException('Unable to create a temporary import directory.');
        }
        return $base;
    }

    /** @param array<string,mixed> $manifest @param array<int,int> $createdMedia @return array<string,array{id:int,url:string}> */
    private function importMedia(string $root, array $manifest, array &$createdMedia, ImportLogger $logger): array
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $assets = array(); $rewriter = new AssetUrlRewriter();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/assets', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || !preg_match('/\.(?:avif|gif|jpe?g|png|webp|woff2?)$/i', $file->getFilename())) { continue; }
            $relative = $rewriter->normalizeReference('', ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/'));
            if (isset($assets[$relative])) { continue; }
            $existing = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_kic_source_asset', 'meta_value' => $relative, 'numberposts' => 1));
            if ($existing) {
                $assets[$relative] = array('id' => (int) $existing[0]->ID, 'url' => (string) wp_get_attachment_url($existing[0]->ID));
                continue;
            }
            $upload = wp_upload_bits($file->getFilename(), null, (string) file_get_contents($file->getPathname()));
            if (!empty($upload['error'])) { throw new RuntimeException('Media import failed: ' . $upload['error']); }
            $type = wp_check_filetype($upload['file']);
            $id = wp_insert_attachment(array('post_mime_type' => $type['type'] ?: 'application/octet-stream', 'post_title' => sanitize_text_field(pathinfo($file->getFilename(), PATHINFO_FILENAME)), 'post_status' => 'inherit'), $upload['file']);
            if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
            if (str_starts_with((string) $type['type'], 'image/') && $type['ext'] !== 'svg') {
                wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
            }
            update_post_meta($id, '_kic_source_asset', $relative);
            update_post_meta($id, '_kic_source_hash', hash_file('sha256', $file->getPathname()));
            $createdMedia[] = (int) $id;
            $assets[$relative] = array('id' => (int) $id, 'url' => (string) wp_get_attachment_url($id));
            $logger->add('info', 'Asset imported.', array('source' => $relative, 'attachment_id' => $id));
        }
        return $assets;
    }

    /** @param array<string,string> $urls */
    private function rewriteAssetUrls(string $content, string $pageFile, array $urls): string
    {
        return (new AssetUrlRewriter())->rewrite($content, $pageFile, $urls);
    }

    /** @param array<string,string> $urls */
    private function rewriteFallbackAssetUrls(string $css, array $urls): string
    {
        return (new AssetUrlRewriter())->rewrite($css, 'assets/css/components.css', $urls, 'fallback-css');
    }

    /** @param array<string,mixed> $seo */
    private function applySeo(int $postId, array $seo, array $assetUrls): void
    {
        update_post_meta($postId, '_yoast_wpseo_title', (string) $seo['title']);
        update_post_meta($postId, '_yoast_wpseo_metadesc', (string) $seo['description']);
        update_post_meta($postId, 'rank_math_title', (string) $seo['title']);
        update_post_meta($postId, 'rank_math_description', (string) $seo['description']);
        update_post_meta($postId, '_kic_canonical_path', (string) $seo['canonical_path']);
        update_post_meta($postId, '_kic_robots', (string) $seo['robots']);
        if (!empty($seo['og_image'])) {
            $normalized = (new AssetUrlRewriter())->normalizeReference('', (string) $seo['og_image']);
            $ogImage = $assetUrls[$normalized] ?? '';
            if ($ogImage === '') { throw new RuntimeException('KIC media rewrite failed: page=manifest; component=seo; location=manifest.og_image; original=' . (string) $seo['og_image'] . '; normalized=' . $normalized . '; rewritten=(none); reason=ZIP media path has no imported attachment mapping.'); }
            update_post_meta($postId, '_kic_og_image', $ogImage);
            update_post_meta($postId, '_yoast_wpseo_opengraph-image', $ogImage);
            update_post_meta($postId, 'rank_math_facebook_image', $ogImage);
        }
    }

    /** @return array{header:int,footer:int} */
    private function createGlobalPatterns(\KIC\Importer\Schema\SiteSchema $schema, KadenceCoreAdapter $adapter, int $importId, array $assetUrls, array $manifest): array
    {
        $pages = $schema->pages();
        if (!$pages) { throw new RuntimeException('No source page is available for the global header and footer.'); }
        $ids = array();
        $backups = array();
        foreach (array('header_html' => 'KIC Site Header', 'footer_html' => 'KIC Site Footer') as $key => $title) {
            $componentId = $key === 'header_html' ? 'site-header' : 'site-footer';
            $content = $this->rewriteAssetUrls($adapter->renderGlobal((string) $pages[0][$key], $componentId, $schema), 'index.html', $assetUrls);
            $content = $this->rewriteInternalLinks($content, $manifest);
            (new AssetUrlRewriter())->assertNoLocalAssetPaths($content, 'index.html', $assetUrls, $componentId);
            $existing = get_page_by_path(sanitize_title($title), OBJECT, 'wp_block');
            $backups[$componentId] = $this->postBackup($existing ? (int) $existing->ID : 0);
            $id = wp_insert_post(wp_slash(array('ID' => $existing ? $existing->ID : 0, 'post_type' => 'wp_block', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => sanitize_title($title), 'post_content' => $content)), true);
            if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
            $backups[$componentId]['id'] = (int) $id;
            update_post_meta((int) $id, '_kic_import_id', $importId);
            $ids[$componentId === 'site-header' ? 'header' : 'footer'] = (int) $id;
            update_option('kic_pattern_backup_' . $importId, $backups, false);
        }
        return $ids;
    }

    /** @return array<string,mixed> */
    private function postBackup(int $postId): array
    {
        if (!$postId) { return array('existed' => false, 'id' => 0); }
        $post = get_post($postId, ARRAY_A);
        return array('existed' => true, 'id' => $postId, 'post' => is_array($post) ? $post : array(), 'meta' => get_post_meta($postId));
    }

    /** @param array<string,array<string,mixed>> $backups @param array<int,int> $importedIds */
    private function restorePageBackups(array $backups, array $importedIds): void
    {
        $restored = array();
        foreach ($backups as $backup) {
            if (!empty($backup['existed']) && !empty($backup['post'])) {
                $this->restorePostBackup($backup);
                $restored[] = (int) $backup['id'];
            }
        }
        foreach ($importedIds as $id) {
            if (!in_array((int) $id, $restored, true)) { wp_delete_post((int) $id, true); }
        }
    }

    private function restorePatternBackups(int $importId): void
    {
        $backups = get_option('kic_pattern_backup_' . $importId, array());
        if (!is_array($backups)) { return; }
        foreach ($backups as $backup) {
            if (!empty($backup['existed']) && !empty($backup['post'])) {
                $this->restorePostBackup($backup);
            } elseif (!empty($backup['id'])) {
                wp_delete_post((int) $backup['id'], true);
            }
        }
        delete_option('kic_pattern_backup_' . $importId);
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

    /** @param array<string,mixed> $manifest @param array<string,int> $pageMap */
    private function createMenus(array $manifest, array $pageMap): void
    {
        foreach (array('primary', 'footer') as $location) {
            $name = 'KIC ' . ucfirst($location);
            $menu = wp_get_nav_menu_object($name);
            $menuId = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu($name);
            if (!$menuId) { continue; }
            foreach ((array) wp_get_nav_menu_items($menuId) as $item) { wp_delete_post($item->ID, true); }
            foreach (($manifest['menus'][$location] ?? array()) as $item) {
                $postId = $pageMap[$item['page_slug']] ?? 0;
                if ($postId) { wp_update_nav_menu_item($menuId, 0, array('menu-item-title' => sanitize_text_field($item['label']), 'menu-item-object' => 'page', 'menu-item-object-id' => $postId, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish')); }
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) { return; }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }

    /** @param array<string,mixed> $manifest */
    private function rewriteInternalLinks(string $content, array $manifest): string
    {
        $links = array();
        foreach ((array) ($manifest['pages'] ?? array()) as $page) {
            $file = '/' . ltrim((string) ($page['file'] ?? ''), '/');
            $slug = (string) ($page['slug'] ?? '/');
            if ($file !== '/') { $links[$file] = home_url($slug); }
        }
        uksort($links, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        return strtr($content, $links);
    }

    /** @param array<string,mixed> $values @param array<string,mixed> $declared @return array<string,string> */
    private function sanitizePlaceholderValues(array $values, array $declared): array
    {
        $result = array();
        foreach ($declared as $name => $description) {
            $value = isset($values[$name]) ? trim((string) $values[$name]) : '';
            $result[(string) $name] = sanitize_text_field($value);
        }
        return $result;
    }
}
