<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Adapter\KadenceCoreAdapter;
use KIC\Importer\Schema\SiteSchemaBuilder;
use PHPUnit\Framework\TestCase;

final class AdapterSerializationTest extends TestCase
{
    public function testVisibleComponentsRemainNativeEditableBlocks(): void
    {
        [$schema, $page] = $this->fixture();
        $adapter = new KadenceCoreAdapter();
        $content = $adapter->renderPage($page, $schema);
        self::assertStringContainsString('wp:kadence/advancedheading', $content);
        self::assertStringContainsString('wp:kadence/advancedbtn', $content);
        self::assertStringContainsString('wp:kadence/rowlayout', $content);
        self::assertStringNotContainsString('wp:html', $content);
    }

    public function testHeaderAndFooterAreEditableBlockPatterns(): void
    {
        [$schema, $page] = $this->fixture();
        $adapter = new KadenceCoreAdapter();
        $header = $adapter->renderGlobal($page['header_html'], 'site-header', $schema);
        $footer = $adapter->renderGlobal($page['footer_html'], 'site-footer', $schema);
        self::assertStringContainsString('wp:kadence/rowlayout', $header);
        self::assertStringContainsString('wp:navigation', $header);
        self::assertStringContainsString('wp:kadence/rowlayout', $footer);
        self::assertStringNotContainsString('wp:html', $header . $footer);
    }

    public function testRelativeImageSrcIsNotCorruptedIntoABareHostUrl(): void
    {
        // Regression test: the KIC header contract's own example uses a bare
        // relative image path with no leading slash (e.g.
        // "assets/images/logo.svg"). esc_url() treats such a value as a
        // scheme-less host and silently prepends "http://" to it, which then
        // looks like an intentional remote URL to AssetUrlRewriter and never
        // gets rewritten to the real Media Library URL - confirmed live as
        // <img src="http://assets/images/logo.png"> on an imported page.
        [$schema] = $this->fixture();
        $adapter = new KadenceCoreAdapter();
        $html = $adapter->renderGlobal(
            '<div><img src="assets/images/logo.png" alt="Logo" width="40" height="40"></div>',
            'site-header',
            $schema
        );
        self::assertStringContainsString('src="assets/images/logo.png"', $html);
        self::assertStringNotContainsString('http://assets/images/logo.png', $html);
    }

    /** @return array{0:\KIC\Importer\Schema\SiteSchema,1:array<string,mixed>} */
    private function fixture(): array
    {
        $root = dirname(__DIR__) . '/fixtures/complete-kic';
        $manifest = json_decode((string) file_get_contents($root . '/site-manifest.json'), true);
        $schema = (new SiteSchemaBuilder())->build($root, $manifest);
        return array($schema, $schema->pages()[0]);
    }
}
