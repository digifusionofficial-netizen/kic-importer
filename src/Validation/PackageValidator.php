<?php

namespace KIC\Importer\Validation;

use DOMDocument;
use DOMXPath;
use KIC\Importer\Contract\KicContract;

final class PackageValidator
{
    /** @var array<string,mixed>|null */
    private ?array $manifest = null;
    /** @var array<string,string> */
    private array $componentIds = array();

    public function manifest(): ?array
    {
        return $this->manifest;
    }

    public function validate(string $root): ValidationResult
    {
        $result = new ValidationResult();
        $root = rtrim(str_replace('\\', '/', $root), '/');
        foreach (KicContract::REQUIRED_FILES as $path) {
            if (!is_file($root . '/' . $path)) {
                $result->addError(new ValidationIssue('required_file_missing', 'Required file is missing.', $path));
            }
        }
        foreach (KicContract::REQUIRED_DIRECTORIES as $path) {
            if (!is_dir($root . '/' . $path)) {
                $result->addError(new ValidationIssue('required_directory_missing', 'Required directory is missing.', $path));
            }
        }
        if (!$result->passed()) {
            return $result;
        }

        $raw = file_get_contents($root . '/site-manifest.json');
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($manifest) || json_last_error() !== JSON_ERROR_NONE) {
            $result->addError(new ValidationIssue('manifest_invalid_json', 'site-manifest.json is not valid JSON.', 'site-manifest.json'));
            return $result;
        }
        $this->manifest = $manifest;
        $this->validateManifest($manifest, $root, $result);
        $this->validateFiles($root, $manifest, $result);
        return $result;
    }

    /** @param array<string,mixed> $manifest */
    private function validateManifest(array $manifest, string $root, ValidationResult $result): void
    {
        if (($manifest['contract_version'] ?? null) !== KicContract::VERSION) {
            $result->addError(new ValidationIssue('contract_version_mismatch', 'Manifest contract_version must be KIC-1.0.', 'site-manifest.json'));
        }
        if (($manifest['target'] ?? null) !== KicContract::TARGET) {
            $result->addError(new ValidationIssue('target_mismatch', 'Manifest target must be wp-kadence-importer.', 'site-manifest.json'));
        }
        foreach (array('site', 'design', 'pages', 'menus', 'forms', 'placeholders', 'redirects') as $key) {
            if (!array_key_exists($key, $manifest)) {
                $result->addError(new ValidationIssue('manifest_key_missing', 'Required manifest key is missing: ' . $key, 'site-manifest.json'));
            }
        }
        foreach (array(
            'design.colors.primary', 'design.colors.secondary', 'design.colors.accent', 'design.colors.text', 'design.colors.heading', 'design.colors.background', 'design.colors.surface', 'design.colors.border',
            'design.typography.heading_font', 'design.typography.body_font', 'design.typography.base_font_size_px', 'design.typography.body_line_height',
            'design.layout.container_width_px', 'design.layout.content_width_px', 'design.layout.tablet_max_px', 'design.layout.mobile_max_px',
            'design.spacing.section_desktop_px', 'design.spacing.section_tablet_px', 'design.spacing.section_mobile_px',
            'design.radii.small_px', 'design.radii.medium_px', 'design.radii.large_px'
        ) as $path) {
            $value = $manifest;
            foreach (explode('.', $path) as $part) { $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : null; }
            if ($value === null || $value === '') { $result->addError(new ValidationIssue('manifest_value_missing', 'Required manifest value is missing: ' . $path, 'site-manifest.json')); }
        }
        if (($manifest['site']['homepage'] ?? null) !== 'index.html') {
            $result->addError(new ValidationIssue('homepage_invalid', 'site.homepage must be index.html.', 'site-manifest.json'));
        }

        $files = array();
        $slugs = array();
        foreach (($manifest['pages'] ?? array()) as $index => $page) {
            if (!is_array($page)) {
                $result->addError(new ValidationIssue('page_invalid', 'Each pages entry must be an object.', 'site-manifest.json'));
                continue;
            }
            $file = (string) ($page['file'] ?? '');
            $slug = (string) ($page['slug'] ?? '');
            if ($file === '' || isset($files[$file])) {
                $result->addError(new ValidationIssue('page_file_duplicate', 'Every page file must be present exactly once.', $file ?: 'site-manifest.json'));
            }
            $files[$file] = true;
            if ($slug === '' || isset($slugs[$slug]) || ($file === 'index.html' ? $slug !== '/' : !preg_match('#^/.+/$#', $slug))) {
                $result->addError(new ValidationIssue('page_slug_invalid', 'Page slugs must be unique and follow KIC path rules.', $file));
            }
            $slugs[$slug] = true;
            if (!is_file($root . '/' . $file)) {
                $result->addError(new ValidationIssue('page_file_missing', 'Manifest page file does not exist.', $file));
            }
            foreach (array('title', 'description', 'canonical_path', 'robots', 'og_title', 'og_description') as $seoKey) {
                if (!isset($page['seo'][$seoKey]) || trim((string) $page['seo'][$seoKey]) === '') {
                    $result->addError(new ValidationIssue('seo_missing', 'Required SEO value is missing: ' . $seoKey, $file));
                }
            }
            if (!empty($page['seo']['og_image']) && !is_file($root . '/' . $page['seo']['og_image'])) {
                $result->addError(new ValidationIssue('og_image_missing', 'Open Graph image does not exist.', (string) $page['seo']['og_image']));
            }
        }
        foreach (($manifest['menus']['primary'] ?? array()) as $item) {
            if (!isset($slugs[$item['page_slug'] ?? ''])) {
                $result->addError(new ValidationIssue('menu_slug_invalid', 'Primary menu references an unknown page slug.', 'site-manifest.json'));
            }
        }
    }

    /** @param array<string,mixed> $manifest */
    private function validateFiles(string $root, array $manifest, ValidationResult $result): void
    {
        $declared = array();
        foreach (($manifest['pages'] ?? array()) as $page) {
            if (is_array($page) && isset($page['file'])) {
                $declared[(string) $page['file']] = $page;
            }
        }
        $htmlFiles = array_merge(glob($root . '/*.html') ?: array(), glob($root . '/pages/*.html') ?: array());
        foreach ($htmlFiles as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file, strlen($root))), '/');
            if (!isset($declared[$relative])) {
                $result->addError(new ValidationIssue('html_not_declared', 'Every HTML file must appear exactly once in manifest pages.', $relative));
                continue;
            }
            $this->validateHtml($file, $relative, $root, $declared[$relative], $manifest, $result);
        }
        $globalCss = (string) file_get_contents($root . '/assets/css/global.css');
        $tokenMap = array(
            '--color-primary' => $manifest['design']['colors']['primary'] ?? null,
            '--color-secondary' => $manifest['design']['colors']['secondary'] ?? null,
            '--color-accent' => $manifest['design']['colors']['accent'] ?? null,
            '--color-text' => $manifest['design']['colors']['text'] ?? null,
            '--color-heading' => $manifest['design']['colors']['heading'] ?? null,
            '--color-background' => $manifest['design']['colors']['background'] ?? null,
            '--color-surface' => $manifest['design']['colors']['surface'] ?? null,
            '--color-border' => $manifest['design']['colors']['border'] ?? null,
            '--container-width' => isset($manifest['design']['layout']['container_width_px']) ? $manifest['design']['layout']['container_width_px'] . 'px' : null,
            '--content-width' => isset($manifest['design']['layout']['content_width_px']) ? $manifest['design']['layout']['content_width_px'] . 'px' : null,
            '--section-space-desktop' => isset($manifest['design']['spacing']['section_desktop_px']) ? $manifest['design']['spacing']['section_desktop_px'] . 'px' : null,
            '--section-space-tablet' => isset($manifest['design']['spacing']['section_tablet_px']) ? $manifest['design']['spacing']['section_tablet_px'] . 'px' : null,
            '--section-space-mobile' => isset($manifest['design']['spacing']['section_mobile_px']) ? $manifest['design']['spacing']['section_mobile_px'] . 'px' : null,
            '--radius-small' => isset($manifest['design']['radii']['small_px']) ? $manifest['design']['radii']['small_px'] . 'px' : null,
            '--radius-medium' => isset($manifest['design']['radii']['medium_px']) ? $manifest['design']['radii']['medium_px'] . 'px' : null,
            '--radius-large' => isset($manifest['design']['radii']['large_px']) ? $manifest['design']['radii']['large_px'] . 'px' : null,
        );
        foreach ($tokenMap as $token => $value) {
            if ($value !== null && !preg_match('/' . preg_quote($token, '/') . '\s*:\s*' . preg_quote((string) $value, '/') . '\s*;/i', $globalCss)) {
                $result->addError(new ValidationIssue('design_token_mismatch', 'CSS token does not match manifest: ' . $token, 'assets/css/global.css'));
            }
        }
        foreach (array('--font-heading' => $manifest['design']['typography']['heading_font'] ?? null, '--font-body' => $manifest['design']['typography']['body_font'] ?? null) as $token => $font) {
            if ($font !== null && !preg_match('/' . preg_quote($token, '/') . '\s*:\s*["\']?' . preg_quote((string) $font, '/') . '["\']?(?:\s*,|\s*;)/i', $globalCss)) {
                $result->addError(new ValidationIssue('design_token_mismatch', 'CSS font token does not match manifest: ' . $token, 'assets/css/global.css'));
            }
        }
        foreach (array('global.css', 'components.css', 'responsive.css') as $css) {
            $text = (string) file_get_contents($root . '/assets/css/' . $css);
            if (preg_match('#@import\s|https?://#i', $text)) {
                $result->addError(new ValidationIssue('remote_dependency', 'Remote CSS dependencies are prohibited.', 'assets/css/' . $css));
            }
        }
        $javascript = (string) file_get_contents($root . '/assets/js/main.js');
        if (preg_match('/\b(fetch|XMLHttpRequest|document\.write|insertAdjacentHTML|createElement)\s*\(|\.innerHTML\s*=/i', $javascript)) {
            $result->addError(new ValidationIssue('javascript_content_generation', 'main.js appears to fetch or generate page content.', 'assets/js/main.js'));
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/assets', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $asset) {
            if ($asset->isFile() && strtolower($asset->getExtension()) === 'svg') {
                $relative = ltrim(str_replace('\\', '/', substr($asset->getPathname(), strlen($root))), '/');
                $result->addError(new ValidationIssue('svg_not_supported', 'Local SVG import is disabled because this plugin version has no audited SVG sanitizer. Convert the SVG to WebP/PNG or remove it before importing.', $relative));
            }
        }
    }

    /** @param array<string,mixed> $page @param array<string,mixed> $manifest */
    private function validateHtml(string $file, string $relative, string $root, array $page, array $manifest, ValidationResult $result): void
    {
        $html = (string) file_get_contents($file);
        if (stripos($html, 'data-kadence-import-contract="KIC-1.0"') === false || stripos($html, 'name="kadence-import-contract" content="KIC-1.0"') === false) {
            $result->addError(new ValidationIssue('contract_marker_missing', 'Both KIC-1.0 HTML markers are required.', $relative));
        }
        if (preg_match('/\sstyle\s*=/i', $html)) {
            $result->addError(new ValidationIssue('inline_style', 'Inline style attributes are prohibited.', $relative));
        }
        if (preg_match('#<(script)[^>]+src=["\']https?://#i', $html)) {
            $result->addError(new ValidationIssue('remote_script', 'Remote scripts are prohibited.', $relative));
        }
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            $result->addError(new ValidationIssue('html_parse_failed', 'HTML could not be parsed.', $relative));
            return;
        }
        $xpath = new DOMXPath($dom);
        foreach (array('//main' => 1, '//header[@data-component="site-header"]' => 1, '//footer[@data-component="site-footer"]' => 1, '//h1[not(ancestor::*[@hidden])]' => 1) as $query => $expected) {
            if ($xpath->query($query)->length !== $expected) {
                $result->addError(new ValidationIssue('document_structure_invalid', 'Page requires exactly one main, site header, site footer, and visible H1.', $relative));
                break;
            }
        }
        $ids = array();
        foreach ($xpath->query('//main/section | //*[@data-component and (self::article or self::div or self::form)]') as $node) {
            $component = $node->getAttribute('data-component');
            $id = $node->getAttribute('data-component-id');
            if (!in_array($component, KicContract::COMPONENTS, true)) {
                $result->addError(new ValidationIssue('component_unknown', 'Unknown strict-mode component: ' . $component, $relative));
            }
            $globalDuplicate = isset($this->componentIds[$id]) && !in_array($id, array('site-header', 'site-footer'), true);
            if ($id === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) || isset($ids[$id]) || $globalDuplicate) {
                $result->addError(new ValidationIssue('component_id_invalid', 'Component IDs must be unique kebab-case values.', $relative));
            }
            $ids[$id] = true;
            $this->componentIds[$id] = $relative;
        }
        foreach ($xpath->query('//main/section') as $section) {
            if (!$section->hasAttribute('data-component') || !$section->hasAttribute('data-component-id')) {
                $result->addError(new ValidationIssue('section_identity_missing', 'Every major section requires component identity.', $relative));
            }
        }
        foreach ($xpath->query('//img') as $image) {
            if (!$image->hasAttribute('src') || !$image->hasAttribute('alt') || !$image->hasAttribute('width') || !$image->hasAttribute('height')) {
                $result->addError(new ValidationIssue('image_attributes_missing', 'Images require src, alt, width, and height.', $relative));
                continue;
            }
            $src = $image->getAttribute('src');
            if (preg_match('#^https?://#i', $src)) {
                $result->addError(new ValidationIssue('hotlinked_image', 'Remote images are prohibited.', $relative));
            } elseif (!$this->localTargetExists($root, $relative, $src)) {
                $result->addError(new ValidationIssue('asset_missing', 'Referenced image does not exist: ' . $src, $relative));
            }
        }
        $title = trim((string) $xpath->evaluate('string(//title)'));
        $description = trim((string) $xpath->evaluate('string(//meta[@name="description"]/@content)'));
        if ($title !== (string) ($page['seo']['title'] ?? '') || $description !== (string) ($page['seo']['description'] ?? '')) {
            $result->addError(new ValidationIssue('seo_mismatch', 'HTML title/description must match manifest SEO.', $relative));
        }
        foreach ($xpath->query('//a[starts-with(@href,"/")]') as $link) {
            $href = preg_replace('/[#?].*$/', '', $link->getAttribute('href'));
            if ($href !== '' && str_ends_with($href, '.html') && !is_file($root . $href)) {
                $result->addError(new ValidationIssue('internal_link_missing', 'Internal HTML target does not exist: ' . $href, $relative));
            }
        }
        foreach ($xpath->query('//form[@data-form-id]') as $form) {
            $formId = $form->getAttribute('data-form-id');
            $found = false;
            foreach (($manifest['forms'] ?? array()) as $definition) {
                $found = $found || (($definition['id'] ?? null) === $formId);
            }
            if (!$found) {
                $result->addError(new ValidationIssue('form_not_declared', 'Form data-form-id is not declared in manifest.', $relative));
            }
        }
        if (preg_match_all('/\{\{([A-Z][A-Z0-9_]*)\}\}/', $html, $matches)) {
            foreach (array_unique($matches[1]) as $token) {
                if (!array_key_exists($token, (array) ($manifest['placeholders'] ?? array()))) {
                    $result->addError(new ValidationIssue('placeholder_not_declared', 'Placeholder is not declared in manifest: ' . $token, $relative));
                }
            }
        }
    }

    private function localTargetExists(string $root, string $page, string $target): bool
    {
        $target = preg_replace('/[#?].*$/', '', $target);
        if ($target === '' || str_starts_with($target, 'data:')) {
            return false;
        }
        $base = str_starts_with($target, '/') ? $root : dirname($root . '/' . $page);
        $candidate = $base . '/' . ltrim($target, '/');
        return is_file($candidate);
    }
}
