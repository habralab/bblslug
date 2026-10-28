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

    /** @dataProvider proseBoundaries */
    public function testOrdinaryProseKeepsContextUnlessTheCapRequiresSplitting(array $units): void
    {
        $value = implode(" \t", array_fill(0, 4, implode(' ', $units)));
        $source = json_encode(['s0' => $value], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $cap = 0;
        foreach ($units as $unit) {
            $encoded = json_encode(['s0' => $unit], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $cap = max($cap, strlen($encoded) + 16);
        }
        self::assertGreaterThan($cap, strlen($source));
        $plan = new SegmentPlan($source, 3, $cap);
        $all = [];
        foreach ($plan->getPayloads() as $payload) {
            self::assertLessThanOrEqual($cap, strlen($payload));
            $packet = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            self::assertLessThanOrEqual(3, count($packet));
            $all += $packet;
        }
        $actual = array_map('trim', array_values($all));
        self::assertSame(array_merge($units, $units, $units, $units), $actual);
        self::assertSame(
            $source,
            $plan->restore(json_encode((object) $all, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
        );
    }

    public static function proseBoundaries(): array
    {
        return [
            'initials' => [['J. R. Tolkien wrote the note.', 'Then he left.']],
            'Russian initials and abbreviations' => [[
                'И. И. Иванов проверил отчёт, т. е. сверил данные и т. д. Затем подписал его.',
            ]],
            'numbered list' => [['1. Read the guide.', '2. Save the result.']],
            'Unicode decimal list' => [['١. Read the guide.', '٢. Save the result.']],
            'lettered list' => [['A. Read the guide.', 'B. Save the result.']],
            'dotted abbreviation' => [['We left at 5 p.m. yesterday.', 'Then it rained.']],
            'lowercase continuation' => [['Use approx. five pages.', 'Then stop.']],
            'ellipsis' => [['Wait... then continue.', 'We agreed.']],
            'quoted continuation' => [['“Go.” she said.', 'Then she left.']],
            'quoted end' => [['She said, “Go now!”', 'Then she closed the door.']],
            'decimals and versions' => [['The value is 3.14 and the build is v1.2.3.', 'Next, use 2.0.']],
            'semicolon and colon' => [['Pack the books; label the box: fragile items go on top.', 'Done.']],
            'year at sentence end' => [['It happened in 2026.', 'Then it stopped.']],
        ];
    }

    public function testInitialContextDoesNotOverrideTheHardByteLimit(): void
    {
        $plan = new SegmentPlan('{"s0":"A. Smith"}', null, 14);
        $all = [];
        foreach ($plan->getPayloads() as $payload) {
            self::assertLessThanOrEqual(14, strlen($payload));
            $all += json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }
        self::assertSame(['A.', 'Smith'], array_values($all));
        self::assertSame('{"s0":"A. Smith"}', $plan->restore(json_encode($all, JSON_THROW_ON_ERROR)));
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

    public function testZeroWordsRemainNonemptyAndPreserveWhitespace(): void
    {
        $source = '{"s0":" 0 0 0 "}';
        $plan = new SegmentPlan($source, 64, 4096);
        self::assertSame([$source], $plan->getPayloads());
        self::assertSame($source, $plan->restore($source));
    }
}
