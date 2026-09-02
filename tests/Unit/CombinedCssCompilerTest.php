<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Style\CombinedCssCompiler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CombinedCssCompilerTest extends TestCase
{
    public function testScopesClassesAndPreservesResponsiveRules(): void
    {
        $result = (new CombinedCssCompiler())->compile(array(
            'assets/css/global.css' => ':root{--x:#fff}.container{max-width:1180px}',
            'assets/css/components.css' => '.grid.grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))}',
            'assets/css/responsive.css' => '@media (max-width:767px){.grid-3{grid-template-columns:1fr}}',
        ), 'kic-site-91');
        self::assertStringContainsString('.kic-site-91{--x:#fff}', $result['css']);
        self::assertStringContainsString('.kic-site-91 .kic-src-container', $result['css']);
        self::assertStringContainsString('@media (max-width:767px)', $result['css']);
        self::assertStringContainsString('.kic-src-grid.kic-src-grid-3', $result['css']);
        self::assertStringContainsString('> .kt-row-column-wrap', $result['css']);
        self::assertSame(4, $result['rules']);
    }

    public function testAsymmetricGridDoesNotNestOnKadencesOuterRowWrapper(): void
    {
        // Regression test: a rule that establishes an asymmetric grid (e.g. a
        // hero split) must apply ONLY to Kadence's inner .kt-row-column-wrap,
        // never also to the bare source-class selector. Kadence's own DOM puts
        // that class on the OUTER row wrapper, one level above the wrapper that
        // actually holds the grid children; applying the same grid-template
        // there too creates a second, incorrectly-nested grid around Kadence's
        // single wrapped child, corrupting the intended column split (observed
        // live as an empty first column and a doubly-shrunk second column).
        $result = (new CombinedCssCompiler())->compile(array(
            'assets/css/components.css' => '.hero-inner{display:grid;grid-template-columns:1.2fr .8fr;align-items:center;gap:56px}',
        ), 'kic-site-7');
        self::assertStringContainsString('.kic-site-7 .kic-src-hero-inner > .kt-row-column-wrap', $result['css']);
        self::assertMatchesRegularExpression('/\.kt-row-column-wrap\{[^}]*grid-template-columns:1\.2fr \.8fr/', $result['css']);
        self::assertDoesNotMatchRegularExpression('/\.kic-src-hero-inner\{[^}]*grid-template-columns/', $result['css']);
        self::assertDoesNotMatchRegularExpression('/\.kic-src-hero-inner,[^{]*\{[^}]*display:grid/', $result['css']);
    }

    public function testSanitizesImportsAndRejectsExecutableCss(): void
    {
        $safe = (new CombinedCssCompiler())->compile(array('global.css' => '@import url("https://bad.test/x.css");p{color:red}'), 'kic-site-1');
        self::assertStringNotContainsString('@import', $safe['css']);
        self::assertNotEmpty($safe['sanitized']);
        $this->expectException(RuntimeException::class);
        (new CombinedCssCompiler())->compile(array('global.css' => 'p{width:expression(alert(1))}'), 'kic-site-1');
    }
}
