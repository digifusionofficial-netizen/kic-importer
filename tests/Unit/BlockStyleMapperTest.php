<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Style\BlockStyleMapper;
use PHPUnit\Framework\TestCase;

final class BlockStyleMapperTest extends TestCase
{
    public function testMapsResponsiveKadenceHeadingAttributes(): void
    {
        $mapper = new BlockStyleMapper();
        $attributes = $mapper->headingAttributes(array(
            'desktop' => array('font-size' => '52px', 'line-height' => '1.1', 'color' => '#111111', 'padding' => '8px 16px'),
            'tablet' => array('font-size' => '42px', 'padding' => '6px 12px'),
            'mobile' => array('font-size' => '34px', 'padding' => '4px 8px'),
        ), 1, 'home-title');
        self::assertSame(52.0, $attributes['size']);
        self::assertSame(42.0, $attributes['tabSize']);
        self::assertSame(34.0, $attributes['mobileSize']);
        self::assertSame(array(8.0, 16.0, 8.0, 16.0), $attributes['padding']);
    }

    public function testUnmappedSupportedPropertyIsReported(): void
    {
        $mapper = new BlockStyleMapper();
        $mapper->groupAttributes(array('desktop' => array('box-shadow' => '0 4px 12px #000'), 'tablet' => array(), 'mobile' => array()), 'card-one');
        self::assertSame('box-shadow', $mapper->fallbacks()[0]['property']);
    }
}
