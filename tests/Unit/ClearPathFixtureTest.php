<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Adapter\KadenceCoreAdapter;
use KIC\Importer\Schema\SiteSchemaBuilder;
use KIC\Importer\Style\CombinedCssCompiler;
use KIC\Importer\Validation\PackageValidator;
use PHPUnit\Framework\TestCase;

final class ClearPathFixtureTest extends TestCase
{
    private string $root;

    protected function setUp(): void { $this->root = dirname(__DIR__) . '/fixtures/clearpath-kic'; }

    public function testCompleteClearPathFixtureRetainsDesignFallback(): void
    {
        $validator = new PackageValidator();
        $validation = $validator->validate($this->root);
        self::assertTrue($validation->passed(), json_encode($validation->toArray()));
        $files = array();
        foreach (array('global.css', 'components.css', 'responsive.css') as $file) { $files['assets/css/' . $file] = (string) file_get_contents($this->root . '/assets/css/' . $file); }
        $result = (new CombinedCssCompiler())->compile($files, 'kic-site-314');
        $css = $result['css'];
        self::assertStringContainsString('--container-width: 1160px', $css);
        self::assertStringContainsString('.kic-site-314 h1{ font-size: 52px;', $css);
        self::assertStringContainsString('.kic-src-features-grid h2', $css);
        self::assertStringContainsString('color: #FFFFFF', $css);
        self::assertStringContainsString('.kic-src-header-inner', $css);
        self::assertStringContainsString('.kic-src-service-card', $css);
        self::assertStringContainsString('.kic-src-faq-question', $css);
        self::assertStringContainsString('.kic-src-contact-form', $css);
        self::assertStringContainsString('.kic-src-cta', $css);
        self::assertStringContainsString('.kic-src-site-footer', $css);
        self::assertStringContainsString('@media(min-width:768px)and(max-width:1199px)', str_replace(' ', '', $css));
        self::assertStringContainsString('@media(max-width:767px)', str_replace(' ', '', $css));
        self::assertGreaterThan(60, $result['rules']);
    }

    public function testClearPathRemainsNativeAndCarriesPermanentScope(): void
    {
        $manifest = json_decode((string) file_get_contents($this->root . '/site-manifest.json'), true);
        $schema = (new SiteSchemaBuilder())->build($this->root, $manifest);
        $adapter = new KadenceCoreAdapter();
        $adapter->configure('kic-site-314', $schema, array());
        $page = $schema->pages()[0];
        $content = $adapter->renderPage($page, $schema);
        $globals = $adapter->renderGlobal($page['header_html'], 'site-header', $schema) . $adapter->renderGlobal($page['footer_html'], 'site-footer', $schema);
        self::assertStringContainsString('kic-site-314', $content . $globals);
        self::assertStringContainsString('wp:kadence/advancedheading', $content);
        self::assertStringContainsString('wp:kadence/accordion', $content);
        self::assertStringContainsString('wp:kic/contact-form', $content);
        self::assertStringContainsString('wp:navigation', $globals);
        self::assertStringContainsString('kic-src-faq-question', $content);
        self::assertStringNotContainsString('wp:html', $content . $globals);
        self::assertNotEmpty($adapter->nativeMappings());
        self::assertNotEmpty($adapter->mappingFallbacks());
    }
}
