<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\SegmentPlan;
use PHPUnit\Framework\TestCase;

final class SegmentPlanTest extends TestCase
{
    public function testBytePackingAndExactRestore(): void
    {
        $source = '{"s0":"Привет \"мир\" one two","s2":"second"}';
        $plan = new SegmentPlan($source, 2, 60);
        $payloads = $plan->getPayloads();
        self::assertNotEmpty($payloads);
        foreach ($payloads as $payload) {
            self::assertLessThanOrEqual(60, strlen($payload));
        }
        $all = [];
        foreach ($payloads as $payload) {
            $all += json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }
        self::assertSame(
            '{"s0":"Привет \\"мир\\" one two","s2":"second"}',
            $plan->restore(json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
        );
    }

    public function testWhitespaceSeparatorsRemainExact(): void
    {
        $source = "{\"s0\":\"alpha  beta\\r\\n gamma\"}";
        $plan = new SegmentPlan($source, null, 24);
        $all = [];
        foreach ($plan->getPayloads() as $payload) {
            $all += json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }
        self::assertSame(
            $source,
            $plan->restore(json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
        );
    }

    public function testLongSentenceUsesByteCapAndRoundTrips(): void
    {
        $source = '{"s0":"one two three four five six seven eight nine ten"}';
        $plan = new SegmentPlan($source, null, 32);
        $all = [];
        foreach ($plan->getPayloads() as $payload) {
            self::assertLessThanOrEqual(32, strlen($payload));
            $all += json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }
        self::assertSame($source, $plan->restore(json_encode($all, JSON_THROW_ON_ERROR)));
    }

    public function testOversizedRepetitionsRemainSeparateSentenceUnits(): void
    {
        $sentence = 'Keep every repeated sentence.';
        $value = implode(" \t", array_fill(0, 29, $sentence));
        $source = json_encode(['s0' => $value, 's1' => 'Untouched. Still here!'], JSON_THROW_ON_ERROR);
        $plan = new SegmentPlan($source, 3, 120);
        $all = [];
        foreach ($plan->getPayloads() as $payload) {
            self::assertLessThanOrEqual(120, strlen($payload));
            $packet = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            self::assertLessThanOrEqual(3, count($packet));
            $all += $packet;
        }
        self::assertSame('Untouched. Still here!', $all['s1']);
        unset($all['s1']);
        self::assertCount(29, $all);
        self::assertSame(array_fill(0, 29, $sentence), array_values($all));
        $all['s1'] = 'Untouched. Still here!';
        self::assertSame($source, $plan->restore(json_encode((object) $all, JSON_THROW_ON_ERROR)));
    }

    public function testFittingSentenceValuesRetainTheirOriginalGranularity(): void
    {
        $source = '{"s0":"Dr. Smith uses v1.2.3. Then continues!"}';
        $plan = new SegmentPlan($source, null, 120);
        self::assertSame([$source], $plan->getPayloads());
    }

    public function testQuotedAndUnicodeSentenceBoundariesPreserveWhitespaceExactly(): void
    {
        $part = "«Привет!»\r\n\"Next?\"\tGo。  Last！\u{00A0}End.";
        $value = " \t" . implode(' ', array_fill(0, 5, $part)) . '  ';
        $source = json_encode(['s0' => $value], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $plan = new SegmentPlan($source, null, 100);
        $all = [];
        foreach ($plan->getPayloads() as $payload) {
            self::assertLessThanOrEqual(100, strlen($payload));
            $all += json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }
        self::assertCount(25, $all);
        self::assertSame(" \t«Привет!»", $all['s0']);
        self::assertSame(
            $source,
            $plan->restore(json_encode((object) $all, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
        );
    }

    public function testGeneratedIdsCrossDigitBoundary(): void
    {
        $words = implode(' ', array_fill(0, 12, 'x'));
        $plan = new SegmentPlan(json_encode(['s0' => $words], JSON_THROW_ON_ERROR), null, 11);
        $all = [];
        foreach ($plan->getPayloads() as $payload) {
            $all += json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }
        self::assertArrayHasKey('s10', $all);
        self::assertSame(
            json_encode(['s0' => $words], JSON_THROW_ON_ERROR),
            $plan->restore(json_encode($all, JSON_THROW_ON_ERROR))
        );
    }

    public function testEscapedWhitespaceAndTrailingWhitespaceStayInChunks(): void
    {
        $source = json_encode(['s0' => "one\t two\n  \\"], JSON_THROW_ON_ERROR);
        $plan = new SegmentPlan($source, null, 30);
        $all = [];
        foreach ($plan->getPayloads() as $payload) {
            $all += json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }
        self::assertSame($source, $plan->restore(json_encode($all, JSON_THROW_ON_ERROR)));
    }

    public function testValueThatFitsKeepsTrailingWhitespaceInPayload(): void
    {
        $plan = new SegmentPlan('{"s0":"hello  "}', null, 30);
        self::assertSame(['{"s0":"hello  "}'], $plan->getPayloads());
    }

    public function testRejectsUnbreakableWordAndInvalidCaps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SegmentPlan('{"s0":"unbreakable"}', null, 10);
    }

    /** @dataProvider invalidInputs */
    public function testRejectsInvalidInputs(string $payload, ?int $count, int $bytes): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SegmentPlan($payload, $count, $bytes);
    }

    /** @return iterable<string,array{string,?int,int}> */
    public static function invalidInputs(): iterable
    {
        yield 'count' => ['{"s0":"ok"}', 0, 100];
        yield 'bytes' => ['{"s0":"ok"}', null, 0];
        yield 'non flat' => ['[]', null, 100];
        yield 'bad id' => ['{"text":"ok"}', null, 100];
        yield 'missing source value' => ['{"s0":""}', null, 100];
    }
}
