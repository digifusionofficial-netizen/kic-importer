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

    public function testSanitizesImportsAndRejectsExecutableCss(): void
    {
        $safe = (new CombinedCssCompiler())->compile(array('global.css' => '@import url("https://bad.test/x.css");p{color:red}'), 'kic-site-1');
        self::assertStringNotContainsString('@import', $safe['css']);
        self::assertNotEmpty($safe['sanitized']);
        $this->expectException(RuntimeException::class);
        (new CombinedCssCompiler())->compile(array('global.css' => 'p{width:expression(alert(1))}'), 'kic-site-1');
    }
}
