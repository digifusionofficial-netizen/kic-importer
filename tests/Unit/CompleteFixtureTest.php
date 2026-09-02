<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Schema\SiteSchemaBuilder;
use KIC\Importer\Validation\PackageValidator;
use PHPUnit\Framework\TestCase;

final class CompleteFixtureTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void { $this->fixture = dirname(__DIR__) . '/fixtures/complete-kic'; }

    public function testCompleteFixturePassesAndBuildsStyledSchema(): void
    {
        $validator = new PackageValidator();
        $result = $validator->validate($this->fixture);
        self::assertTrue($result->passed(), json_encode($result->toArray()));
        $schema = (new SiteSchemaBuilder())->build($this->fixture, $validator->manifest());
        self::assertCount(1, $schema->pages());
        self::assertNotEmpty($schema->stylesheet()->rules('desktop'));
        self::assertNotEmpty($schema->stylesheet()->rules('tablet'));
        self::assertNotEmpty($schema->stylesheet()->rules('mobile'));
    }
}
