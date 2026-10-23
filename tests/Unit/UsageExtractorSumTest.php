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
}
