<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Import\AssetUrlRewriter;
use PHPUnit\Framework\TestCase;

final class AssetUrlRewriterTest extends TestCase
{
    private AssetUrlRewriter $rewriter;

    protected function setUp(): void { $this->rewriter = new AssetUrlRewriter(); }

    public function testNormalizesHomepageAndNestedPageReferencesToSameUniquePath(): void
    {
        self::assertSame('assets/images/image.png', $this->rewriter->normalizeReference('index.html', 'assets/images/image.png'));
        self::assertSame('assets/images/image.png', $this->rewriter->normalizeReference('pages/services.html', '../assets/images/image.png'));
        self::assertSame('assets/images/image.png', $this->rewriter->normalizeReference('pages/services.html', '..\\assets\\images\\image.png'));
    }

    public function testRewritesHtmlSrcsetCssOgImageAndEscapedBlockAttributes(): void
    {
        $map = array('assets/images/hero-home.png' => 'https://example.test/wp-content/uploads/hero-home.png');
        $source = '<meta property="og:image" content="../assets/images/hero-home.png"><picture><source srcset="../assets/images/hero-home.png 1x"><img src="../assets/images/hero-home.png" srcset="../assets/images/hero-home.png 1x, /assets/images/hero-home.png 2x"></picture><style>.hero{background-image:url(../assets/images/hero-home.png)}</style><!-- wp:kadence/image {"url":"..\/assets\/images\/hero-home.png"} /-->';
        $result = $this->rewriter->rewrite($source, 'pages/services.html', $map, 'services-hero');
        self::assertSame(7, substr_count(str_replace('\\/', '/', $result), 'https://example.test/wp-content/uploads/hero-home.png'));
        self::assertStringNotContainsString('http:/https', $result);
        self::assertStringNotContainsString('assets/images/', str_replace('\\/', '/', $result));
        $this->rewriter->assertNoLocalAssetPaths($result, 'pages/services.html', $map, 'services-hero');
    }

    public function testDoesNotTreatScriptsStylesheetsOrInternalLinksAsMedia(): void
    {
        $content = '<script src="../assets/js/main.js"></script><link rel="stylesheet" href="../assets/css/global.css"><a href="../pages/contact.html">Contact</a>';
        $this->rewriter->assertNoLocalAssetPaths($content, 'pages/services.html', array(), 'page');
        self::assertSame($content, $this->rewriter->rewrite($content, 'pages/services.html', array(), 'page'));
    }

    public function testRejectsPlainAndEncodedTraversalOutsideZipRoot(): void
    {
        foreach (array('../../assets/images/image.png', '%2e%2e/%2e%2e/assets/images/image.png', '%252e%252e/%252e%252e/assets/images/image.png') as $path) {
            try { $this->rewriter->normalizeReference('pages/services.html', $path); self::fail('Traversal was accepted: ' . $path); }
            catch (\RuntimeException $error) { self::assertStringContainsString('escapes the extracted ZIP root', $error->getMessage()); }
        }
    }

    public function testDiagnosticIdentifiesPageComponentAttributeAndNormalizedPath(): void
    {
        try {
            $this->rewriter->assertNoLocalAssetPaths('<img src="../assets/images/missing.png">', 'pages/services.html', array(), 'services-hero');
            self::fail('Unrewritten media was accepted.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('page=pages/services.html', $error->getMessage());
            self::assertStringContainsString('component=services-hero', $error->getMessage());
            self::assertStringContainsString('location=html.src', $error->getMessage());
            self::assertStringContainsString('normalized=assets/images/missing.png', $error->getMessage());
        }
    }

    public function testRejectsPreviouslyGeneratedMalformedAbsoluteUrl(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('original=http:/https://example.test/wp-content/uploads/image.png');
        $this->rewriter->assertNoLocalAssetPaths('<img src="http:/https://example.test/wp-content/uploads/image.png">', 'index.html', array(), 'home-hero');
    }
}
