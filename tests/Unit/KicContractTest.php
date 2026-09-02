<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Contract\KicContract;
use PHPUnit\Framework\TestCase;

final class KicContractTest extends TestCase
{
    public function testContractIdentityIsFrozen(): void
    {
        self::assertSame('KIC-1.0', KicContract::VERSION);
        self::assertSame('wp-kadence-importer', KicContract::TARGET);
        self::assertSame('cb45bc42bdeb60120e1ebf00168a427d4420520d578725077c1655434f83f63f', KicContract::FINGERPRINT);
    }

    public function testRequiredFilesMatchContractRoot(): void
    {
        self::assertContains('index.html', KicContract::REQUIRED_FILES);
        self::assertContains('site-manifest.json', KicContract::REQUIRED_FILES);
        self::assertContains('assets/js/main.js', KicContract::REQUIRED_FILES);
    }
}
