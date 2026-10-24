<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use Bblslug\Models\UsageExtractor;
use PHPUnit\Framework\TestCase;

final class UsageExtractorSumTest extends TestCase
{
    public function testSumsHeterogeneousMetricsWithoutInventingMissingOnes(): void
    {
        $usages = [
            ['tokens' => ['total' => 10, 'breakdown' => ['prompt' => 7, 'completion' => 3]]],
            ['tokens' => ['breakdown' => ['prompt' => 2]], 'characters' => ['total' => 4]],
            ['tokens' => ['total' => 5, 'breakdown' => ['completion' => 1, 'cached' => 4]]],
        ];

        self::assertSame([
            'tokens' => [
                'total' => 15,
                'breakdown' => ['prompt' => 9, 'completion' => 4, 'cached' => 4],
            ],
            'characters' => ['total' => 4],
        ], UsageExtractor::sum($usages));
    }

    public function testEmptyInputStaysEmptyAndInputIsNotMutated(): void
    {
        self::assertSame([], UsageExtractor::sum([]));
        $usages = [['tokens' => ['total' => 2, 'breakdown' => ['prompt' => 2]]]];
        $before = $usages;
        UsageExtractor::sum($usages);
        self::assertSame($before, $usages);
    }

    public function testOverflowOmitsOnlyTheMetricAndDoesNotRestartIt(): void
    {
        $maximum = PHP_INT_MAX;
        $usages = [
            [
                'tokens' => [
                    'total' => $maximum,
                    'breakdown' => [
                        'prompt' => $maximum - 1,
                        'completion' => $maximum,
                        'cached' => 2,
                    ],
                ],
                'characters' => ['total' => 100],
                'overflowOnly' => ['total' => $maximum],
                'breakdownOnly' => ['breakdown' => ['x' => $maximum]],
                'emptyBreakdown' => ['total' => $maximum, 'breakdown' => []],
            ],
            [
                'tokens' => [
                    'total' => 0,
                    'breakdown' => [
                        'prompt' => 1,
                        'completion' => 1,
                        'cached' => 3,
                    ],
                ],
                'characters' => ['total' => 1],
                'overflowOnly' => ['total' => 1],
                'breakdownOnly' => ['breakdown' => ['x' => 1]],
                'emptyBreakdown' => ['total' => 1, 'breakdown' => []],
            ],
            [
                'tokens' => [
                    'total' => 1,
                    'breakdown' => [
                        'prompt' => 0,
                        'completion' => 9,
                        'cached' => 4,
                    ],
                ],
                'characters' => ['total' => 2],
                'overflowOnly' => ['total' => 2],
                'breakdownOnly' => ['breakdown' => ['x' => 0]],
                'emptyBreakdown' => ['total' => 2, 'breakdown' => []],
            ],
        ];
        $before = $usages;

        self::assertSame([
            'tokens' => [
                // The total overflowed after its first value, so later values do not restart it.
                'breakdown' => [
                    'prompt' => $maximum,
                    'cached' => 9,
                ],
            ],
            'characters' => ['total' => 103],
        ], UsageExtractor::sum($usages));
        self::assertSame($before, $usages);
    }

    public function testValidNonnegativeSumsAreIndependentOfPacketOrderAndKeepBoundaryValues(): void
    {
        $maximum = PHP_INT_MAX;
        $usages = [
            ['tokens' => ['total' => $maximum - 1, 'breakdown' => ['prompt' => 4]]],
            ['tokens' => ['total' => 1, 'breakdown' => ['prompt' => 6]]],
            ['characters' => ['total' => 0]],
        ];

        self::assertEquals(UsageExtractor::sum($usages), UsageExtractor::sum(array_reverse($usages)));
        self::assertSame([
            'tokens' => ['total' => $maximum, 'breakdown' => ['prompt' => 10]],
            'characters' => ['total' => 0],
        ], UsageExtractor::sum($usages));
    }
}
