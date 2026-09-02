<?php

namespace KIC\Importer\Tests\Unit;

use KIC\Importer\Validation\ValidationIssue;
use KIC\Importer\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

final class ValidationResultTest extends TestCase
{
    public function testNewResultPasses(): void
    {
        self::assertTrue((new ValidationResult())->passed());
    }

    public function testErrorMakesResultFail(): void
    {
        $result = new ValidationResult();
        $result->addError(new ValidationIssue('missing_file', 'Required file is missing.', 'index.html'));

        self::assertFalse($result->passed());
        self::assertSame('fail', $result->toArray()['status']);
    }
}
