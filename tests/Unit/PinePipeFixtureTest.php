<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Adapter\KadenceCoreAdapter;
use KIC\Importer\Import\AssetUrlRewriter;
use KIC\Importer\Schema\SiteSchemaBuilder;
use KIC\Importer\Validation\PackageValidator;
use PHPUnit\Framework\TestCase;

final class PinePipeFixtureTest extends TestCase
{
    private string $root;

    protected function setUp(): void { $this->root = dirname(__DIR__) . '/fixtures/pine-pipe-kic'; }

    public function testPinePipePackagePassesPreflightAndParsesAllStyles(): void
    {
        $validator = new PackageValidator();
        $result = $validator->validate($this->root);
        self::assertTrue($result->passed(), json_encode($result->toArray(), JSON_PRETTY_PRINT));
        $schema = (new SiteSchemaBuilder())->build($this->root, $validator->manifest());
        self::assertCount(3, $schema->pages());
        self::assertNotEmpty($schema->stylesheet()->rules('desktop'));
        self::assertNotEmpty($schema->stylesheet()->rules('tablet'));
        self::assertNotEmpty($schema->stylesheet()->rules('mobile'));
    }

    public function testPinePipePagesUseNativeKadenceDesignBlocks(): void
    {
        $manifest = json_decode((string) file_get_contents($this->root . '/site-manifest.json'), true);
        $schema = (new SiteSchemaBuilder())->build($this->root, $manifest);
        $adapter = new KadenceCoreAdapter();
        $adapter->configure('kic-site-42', $schema);
        $content = '';
        foreach ($schema->pages() as $page) { $content .= $adapter->renderPage($page, $schema); }
        foreach (array('wp:kadence/rowlayout', 'wp:kadence/column', 'wp:kadence/advancedheading', 'wp:kadence/advancedbtn', 'wp:kadence/singlebtn', 'wp:kadence/image', 'wp:kic/contact-form') as $block) {
            self::assertStringContainsString($block, $content, $block . ' was not serialized.');
        }
        self::assertStringNotContainsString('wp:html', $content);
        self::assertStringContainsString('"tabletLayout":"two-grid"', $content);
        self::assertStringContainsString('"mobileLayout":"row"', $content);
        self::assertStringContainsString('"maxWidth":[1180', $content);
        self::assertStringContainsString('kic-site-42', $content);
        self::assertStringContainsString('wp:kadence/accordion', $content);
        self::assertStringContainsString('wp:kadence/pane', $content);
        self::assertStringNotContainsString('wp:details', $content);
        self::assertStringContainsString('"lineType":""', $content);
        self::assertStringContainsString('kic-src-container', $content);
        self::assertDoesNotMatchRegularExpression('/class="[^"]*(?:^|\s)(?:container|grid|button-group|service-card)(?:\s|$)/', $content);
    }

    public function testPinePipeHeaderFooterAreNativeEditablePatterns(): void
    {
        $manifest = json_decode((string) file_get_contents($this->root . '/site-manifest.json'), true);
        $schema = (new SiteSchemaBuilder())->build($this->root, $manifest);
        $adapter = new KadenceCoreAdapter();
        $page = $schema->pages()[0];
        $patterns = $adapter->renderGlobal($page['header_html'], 'site-header', $schema) . $adapter->renderGlobal($page['footer_html'], 'site-footer', $schema);
        self::assertStringContainsString('wp:kadence/rowlayout', $patterns);
        self::assertStringContainsString('wp:kadence/column', $patterns);
        self::assertStringContainsString('wp:navigation', $patterns);
        self::assertStringContainsString('Pine &amp; Pipe Plumbing', $patterns);
        self::assertStringNotContainsString('wp:html', $patterns);
    }

    public function testFinalPineBlocksAndPatternsContainNoZipRelativeMediaUrls(): void
    {
        $manifest = json_decode((string) file_get_contents($this->root . '/site-manifest.json'), true);
        $schema = (new SiteSchemaBuilder())->build($this->root, $manifest);
        $adapter = new KadenceCoreAdapter(); $adapter->configure('kic-site-42', $schema);
        $map = array();
        foreach (glob($this->root . '/assets/images/*') ?: array() as $file) { $map['assets/images/' . basename($file)] = 'https://example.test/wp-content/uploads/' . basename($file); }
        $rewriter = new AssetUrlRewriter();
        foreach ($schema->pages() as $page) {
            $source = (string) $page['definition']['file'];
            $stored = $rewriter->rewrite($adapter->renderPage($page, $schema), $source, $map, (string) $page['definition']['slug']);
            $rewriter->assertNoLocalAssetPaths($stored, $source, $map, (string) $page['definition']['slug']);
            self::assertStringNotContainsString('assets/images/', str_replace('\\/', '/', $stored));
        }
        $first = $schema->pages()[0];
        foreach (array('header_html' => 'site-header', 'footer_html' => 'site-footer') as $key => $component) {
            $stored = $rewriter->rewrite($adapter->renderGlobal($first[$key], $component, $schema), 'index.html', $map, $component);
            $rewriter->assertNoLocalAssetPaths($stored, 'index.html', $map, $component);
        }
    }
}
