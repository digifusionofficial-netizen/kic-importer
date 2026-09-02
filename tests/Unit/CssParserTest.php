<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Style\CssParser;
use PHPUnit\Framework\TestCase;

final class CssParserTest extends TestCase
{
    public function testParsesTokensDesktopAndResponsiveRules(): void
    {
        $css = ':root{--color-primary:#123456}.hero{padding:96px;color:var(--color-primary)}@media (max-width:1199px){.hero{padding:72px}}@media (max-width:767px){.hero{padding:48px}}';
        $sheet = (new CssParser())->parseFiles(array('test.css' => $css));
        self::assertSame('#123456', $sheet->variables()['--color-primary']);
        $desktopHero = array_values(array_filter($sheet->rules('desktop'), static fn (array $rule): bool => $rule['selector'] === '.hero'))[0];
        self::assertSame('96px', $desktopHero['declarations']['padding']);
        self::assertSame('72px', $sheet->rules('tablet')[0]['declarations']['padding']);
        self::assertSame('48px', $sheet->rules('mobile')[0]['declarations']['padding']);
    }

    public function testReportsUnsupportedProperty(): void
    {
        $sheet = (new CssParser())->parseFiles(array('test.css' => '.hero{clip-path:circle(50%)}'));
        self::assertSame('clip-path', $sheet->unsupported()[0]['property']);
    }
}
