<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\SegmentBatches;
use PHPUnit\Framework\TestCase;

final class SegmentBatchesTest extends TestCase
{
    public function testSplitsWithoutRenumberingAndPreservesOrder(): void
    {
        self::assertSame(
            ['{"s0":"one","s2":"two"}', '{"s5":"three"}'],
            SegmentBatches::split('{"s0":"one","s2":"two","s5":"three"}', 2)
        );
    }

    public function testCeilingBoundariesAndLargeSize(): void
    {
        $payload = '{"s0":"0","s1":"1","s2":"2","s3":"3","s4":"4"}';
        self::assertCount(5, SegmentBatches::split($payload, 1));
        self::assertCount(1, SegmentBatches::split($payload, 25));
        self::assertCount(1, SegmentBatches::split($payload, 100));
    }

    public function testPreservesUnicodeAndEscapes(): void
    {
        self::assertSame(
            ['{"s0":"Привет \\"мир\\" ©/ok"}'],
            SegmentBatches::split('{"s0":"Привет \\"мир\\" \\u00a9/ok"}', 1)
        );
    }

    public function testEmptyObjectReturnsNoBatches(): void
    {
        self::assertSame([], SegmentBatches::split('{}', 1));
    }

    /** @dataProvider invalidPayloads */
    public function testRejectsInvalidInput(string $payload, int $size): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SegmentBatches::split($payload, $size);
    }

    /** @return iterable<string, array{string, int}> */
    public static function invalidPayloads(): iterable
    {
        yield 'zero size' => ['{"s0":"ok"}', 0];
        yield 'negative size' => ['{"s0":"ok"}', -1];
        yield 'malformed json' => ['{"s0":"ok"', 1];
        yield 'array' => ['["ok"]', 1];
        yield 'nested value' => ['{"s0":{"nested":"x"}}', 1];
        yield 'empty value' => ['{"s0":""}', 1];
        yield 'unexpected marker' => ['{"s0":"new @@0@@ marker"}', 1];
        yield 'duplicate key' => ['{"s0":"one","s0":"two"}', 1];
        yield 'invalid id' => ['{"text":"ok"}', 1];
    }
}
