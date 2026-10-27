<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\SegmentPlan;
use PHPUnit\Framework\TestCase;

final class SegmentAbbreviationsTest extends TestCase
{
    public function testHintsKeepContextAcrossSentenceBoundaries(): void
    {
        $text = 'Dr. Smith met Prof. Jones. Another reviewer agreed.';
        $source = json_encode(['s0' => implode(' ', array_fill(0, 5, $text))], JSON_THROW_ON_ERROR);
        $plan = new SegmentPlan($source, 2, 100, ['dr.', 'PROF.']);
        $values = $this->flatten($plan, 100, 2);
        self::assertSame(array_merge(...array_fill(0, 5, [
            'Dr. Smith met Prof. Jones.', 'Another reviewer agreed.',
        ])), array_values($values));
        self::assertSame($source, $plan->restore(json_encode((object) $values, JSON_THROW_ON_ERROR)));
    }

    public function testPairMovesTogetherWhenOnlyTheRemainingSpaceIsInsufficient(): void
    {
        $source = '{"s0":"alpha beta gamma Dr. Smith"}';
        $plan = new SegmentPlan($source, 1, 33, ['Dr.']);
        $values = $this->flatten($plan, 33, 1);
        self::assertSame(['alpha beta gamma', 'Dr. Smith'], array_values($values));
        self::assertSame($source, $plan->restore(json_encode((object) $values, JSON_THROW_ON_ERROR)));
    }

    public function testConsecutiveHintsMoveAsOneRunAndPreserveSeparators(): void
    {
        $source = json_encode(
            ['s0' => "alpha beta gamma\tDr.  Prof.\u{00A0}Smith"],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $plan = new SegmentPlan($source, 1, 40, ['dr.', 'prof.']);
        $values = $this->flatten($plan, 40, 1);
        self::assertSame(['alpha beta gamma', "Dr.  Prof.\u{00A0}Smith"], array_values($values));
        self::assertSame($source, $plan->restore(json_encode(
            (object) $values,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        )));
    }

    public function testQuotesAndUnicodeCaseMatchingKeepOriginalTokens(): void
    {
        $text = '(Dr.) Smith visited ул. Ленина. Another sentence follows.';
        $source = json_encode(
            ['s0' => implode(' ', array_fill(0, 4, $text))],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $plan = new SegmentPlan($source, 3, 150, ['DR.', 'УЛ.', 'dr.']);
        $values = $this->flatten($plan, 150, 3);
        self::assertSame(array_merge(...array_fill(0, 4, [
            '(Dr.) Smith visited ул. Ленина.', 'Another sentence follows.',
        ])), array_values($values));
        self::assertSame($source, $plan->restore(json_encode(
            (object) $values,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        )));
    }

    public function testHardCapStillSplitsAnOversizedRun(): void
    {
        $plan = new SegmentPlan('{"s0":"Dr. Smith"}', null, 14, ['Dr.']);
        $values = $this->flatten($plan, 14);
        self::assertSame(['Dr.', 'Smith'], array_values($values));
        self::assertSame('{"s0":"Dr. Smith"}', $plan->restore(json_encode((object) $values, JSON_THROW_ON_ERROR)));
    }

    public function testFittingValuesAreUnchangedAndGeneratedIdWidthIsRespected(): void
    {
        $source = '{"s0":"Dr. Smith"}';
        self::assertSame([$source], (new SegmentPlan($source, null, 100, ['Dr.']))->getPayloads());
        $input = '{"s0":"alpha beta gamma Dr. Smith","s999":"fixed"}';
        $plan = new SegmentPlan($input, null, 33, ['Dr.']);
        $values = $this->flatten($plan, 33);
        self::assertArrayHasKey('s1000', $values);
        self::assertSame($input, $plan->restore(json_encode((object) $values, JSON_THROW_ON_ERROR)));
    }

    public function testHintsDoNotMergeSeparateOriginalValues(): void
    {
        $source = '{"s0":"Dr.","s1":"Smith"}';
        $plan = new SegmentPlan($source, null, 40, ['Dr.']);
        self::assertSame([$source], $plan->getPayloads());
        self::assertSame($source, $plan->restore($source));
    }

    /** @dataProvider invalidHints */
    public function testInvalidHintsFailEvenForEmptySource(array $hints): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SegmentPlan('{}', null, 100, $hints);
    }

    public static function invalidHints(): array
    {
        return [[['']], [['Dr']], [['Dr. Smith']], [[1]], [['x' => 'Dr.']], [["Dr.\0"]], [["\xff."]], [['.']]];
    }

    private function flatten(SegmentPlan $plan, int $bytes, ?int $count = null): array
    {
        $all = [];
        foreach ($plan->getPayloads() as $payload) {
            self::assertLessThanOrEqual($bytes, strlen($payload));
            $packet = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if ($count !== null) {
                self::assertLessThanOrEqual($count, count($packet));
            }
            $all += $packet;
        }
        return $all;
    }
}
