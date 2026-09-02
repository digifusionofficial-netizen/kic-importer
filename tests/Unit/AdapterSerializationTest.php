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

    /** @return array{0:\KIC\Importer\Schema\SiteSchema,1:array<string,mixed>} */
    private function fixture(): array
    {
        $root = dirname(__DIR__) . '/fixtures/complete-kic';
        $manifest = json_decode((string) file_get_contents($root . '/site-manifest.json'), true);
        $schema = (new SiteSchemaBuilder())->build($root, $manifest);
        return array($schema, $schema->pages()[0]);
    }
}
