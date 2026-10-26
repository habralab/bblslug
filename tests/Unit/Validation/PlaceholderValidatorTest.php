<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use Bblslug\Validation\PlaceholderValidator;
use PHPUnit\Framework\TestCase;

final class PlaceholderValidatorTest extends TestCase
{
    public function testMarkerOrderAndTranslatedTextMayChange(): void
    {
        $validator = new PlaceholderValidator('Hello @@0@@ then @@1@@ and @@0@@');
        self::assertTrue($validator->validate('@@1@@ Перевод @@0@@@@0@@')->isValid());
    }

    public function testEachMarkerCountMustMatchRatherThanOnlyTheTotalCount(): void
    {
        $validator = new PlaceholderValidator('@@0@@ @@1@@');
        $result = $validator->validate('@@0@@ @@0@@');
        self::assertFalse($result->isValid());
        self::assertCount(2, $result->getErrors());
        self::assertStringContainsString('@@0@@: expected 1 occurrence(s), found 2', $result->getErrors()[0]);
        self::assertStringContainsString('@@1@@: expected 1 occurrence(s), found 0', $result->getErrors()[1]);
    }

    public function testUnknownMarkersAreRejectedIncludingDifferentLeadingZeroSpelling(): void
    {
        $validator = new PlaceholderValidator('@@0@@');
        foreach (['@@0@@ @@99@@', '@@00@@', '@@ 0 @@'] as $content) {
            self::assertFalse($validator->validate($content)->isValid(), $content);
        }
    }

    public function testNewMarkersAreRejectedWhenPreparedInputContainsNone(): void
    {
        $validator = new PlaceholderValidator('Hello');
        self::assertTrue($validator->validate('Перевод')->isValid());
        $result = $validator->validate('Перевод @@0@@');
        self::assertFalse($result->isValid());
        self::assertStringContainsString('unexpected marker', $result->getErrors()[0]);
    }

    public function testEveryOccurrenceOfAnExpectedRepeatedMarkerIsRequired(): void
    {
        $validator = new PlaceholderValidator('@@10@@ @@10@@');
        self::assertFalse($validator->validate('@@10@@')->isValid());
        self::assertTrue($validator->validate('@@10@@@@10@@')->isValid());
    }
}
