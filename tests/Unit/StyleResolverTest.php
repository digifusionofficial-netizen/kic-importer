<?php

namespace KIC\Importer\Tests\Unit;

use DOMDocument;
use DOMXPath;
use KIC\Importer\Style\CssParser;
use KIC\Importer\Style\StyleResolver;
use PHPUnit\Framework\TestCase;

final class StyleResolverTest extends TestCase
{
    public function testSimpleSelectorDoesNotLeakIntoDescendants(): void
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<section class="hero"><div class="copy"><h1>Title</h1></div></section>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $hero = $xpath->query('//section')->item(0);
        $heading = $xpath->query('//h1')->item(0);
        $resolver = new StyleResolver((new CssParser())->parseFiles(array('test.css' => '.hero{padding:80px}.hero h1{font-size:52px}')));
        self::assertSame('80px', $resolver->resolve($hero)['desktop']['padding']);
        self::assertArrayNotHasKey('padding', $resolver->resolve($heading)['desktop']);
        self::assertSame('52px', $resolver->resolve($heading)['desktop']['font-size']);
    }
}
