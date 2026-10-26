<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models;

use PHPUnit\Framework\TestCase;
use Bblslug\Models\UsageExtractor;

/**
 * Atomic tests for UsageExtractor:
 * - returns empty when map is missing or raw usage is null
 * - extracts totals and breakdowns for several vendor-shaped payloads
 * - supports dot-notation paths
 * - non-numeric or missing values become 0; numeric strings are accepted
 */
final class UsageExtractorTest extends TestCase
{
    /** @test */
    public function returnsEmptyWhenNoMapOrNullRaw(): void
    {
        $this->assertSame([], UsageExtractor::extract(['vendor' => 'openai'], null));
        $this->assertSame([], UsageExtractor::extract(['vendor' => 'openai'], ['total_tokens' => 10]));
        $this->assertSame([], UsageExtractor::extract([], null));
    }

    /** @test */
    public function openAiStyleExtraction(): void
    {
        // Driver returns usage array like this (OpenAI-compatible)
        $raw = [
            'prompt_tokens'     => 11,
            'completion_tokens' => 7,
            'total_tokens'      => 18,
        ];

        $modelConfig = [
            'vendor' => 'openai',
            'usage' => [
                'tokens' => [
                    'total' => 'total_tokens',
                    'breakdown' => [
                        'prompt'     => 'prompt_tokens',
                        'completion' => 'completion_tokens',
                    ],
                ],
            ],
        ];

        $out = UsageExtractor::extract($modelConfig, $raw);

        $this->assertSame(18, $out['tokens']['total']);
        $this->assertSame(11, $out['tokens']['breakdown']['prompt']);
        $this->assertSame(7, $out['tokens']['breakdown']['completion']);
    }

    /** @test */
    public function anthropicStyleExtraction(): void
    {
        $raw = [
            'input_tokens'  => 5,
            'output_tokens' => 3,
            'total_tokens'  => 8,
        ];

        $modelConfig = [
            'vendor' => 'anthropic',
            'usage' => [
                'tokens' => [
                    'total' => 'total_tokens',
                    'breakdown' => [
                        'prompt'     => 'input_tokens',
                        'completion' => 'output_tokens',
                    ],
                ],
            ],
        ];

        $out = UsageExtractor::extract($modelConfig, $raw);

        $this->assertSame(8, $out['tokens']['total']);
        $this->assertSame(5, $out['tokens']['breakdown']['prompt']);
        $this->assertSame(3, $out['tokens']['breakdown']['completion']);
    }

    /** @test */
    public function googleStyleExtraction(): void
    {
        // Google driver returns usageMetadata as "usage" slice:
        $raw = [
            'totalTokenCount'      => 42,
            'promptTokenCount'     => 30,
            'candidatesTokenCount' => 12,
        ];

        $modelConfig = [
            'vendor' => 'google',
            'usage' => [
                'tokens' => [
                    'total' => 'totalTokenCount',
                    'breakdown' => [
                        'prompt'     => 'promptTokenCount',
                        'completion' => 'candidatesTokenCount',
                    ],
                ],
            ],
        ];

        $out = UsageExtractor::extract($modelConfig, $raw);

        $this->assertSame(42, $out['tokens']['total']);
        $this->assertSame(30, $out['tokens']['breakdown']['prompt']);
        $this->assertSame(12, $out['tokens']['breakdown']['completion']);
    }

    /** @test */
    public function yandexStyleExtraction(): void
    {
        // Yandex driver returns result.usage as "usage" slice:
        $raw = [
            'inputTextTokens'  => 9,
            'completionTokens' => 4,
            'totalTokens'      => 13,
        ];

        $modelConfig = [
            'vendor' => 'yandex',
            'usage' => [
                'tokens' => [
                    'total' => 'totalTokens',
                    'breakdown' => [
                        'prompt'     => 'inputTextTokens',
                        'completion' => 'completionTokens',
                    ],
                ],
            ],
        ];

        $out = UsageExtractor::extract($modelConfig, $raw);

        $this->assertSame(13, $out['tokens']['total']);
        $this->assertSame(9, $out['tokens']['breakdown']['prompt']);
        $this->assertSame(4, $out['tokens']['breakdown']['completion']);
    }

    /** @test */
    public function deepLReturnsEmptyBecauseNoUsage(): void
    {
        $modelConfig = [
            'vendor' => 'deepl',
            'usage'  => [
                'tokens' => [
                    'total' => 'totalTokens',
                ],
            ],
        ];

        // DeepL driver returns null usage -> extractor should return []
        $out = UsageExtractor::extract($modelConfig, null);
        $this->assertSame([], $out);
    }

    /** @test */
    public function dotNotationPathsWork(): void
    {
        $raw = [
            'outer' => [
                'inner' => [
                    'value' => '12', // numeric string is accepted
                ],
            ],
        ];

        $modelConfig = [
            'vendor' => 'custom',
            'usage' => [
                'tokens' => [
                    'total' => 'outer.inner.value',
                ],
            ],
        ];

        $out = UsageExtractor::extract($modelConfig, $raw);

        $this->assertSame(12, $out['tokens']['total']);
    }

    /** @test */
    public function missingOrNonNumericBecomesZero(): void
    {
        $raw = [
            'a' => '12x',     // not purely numeric string
            'b' => ['c' => []], // not numeric
            // 'missing' is absent
        ];

        $modelConfig = [
            'vendor' => 'custom',
            'usage' => [
                'tokens' => [
                    'total' => 'missing',
                    'breakdown' => [
                        'x' => 'a',
                        'y' => 'b.c',
                    ],
                ],
            ],
        ];

        $out = UsageExtractor::extract($modelConfig, $raw);

        $this->assertSame(0, $out['tokens']['total']);
        $this->assertSame(0, $out['tokens']['breakdown']['x']);
        $this->assertSame(0, $out['tokens']['breakdown']['y']);
    }

    /** @dataProvider countValues */
    public function testOnlyRepresentableNonnegativeWholeCountsAreExtracted(mixed $value, int $expected): void
    {
        $config = ['usage' => ['tokens' => [
            'total' => 'count', 'breakdown' => ['invalid' => 'count', 'valid' => 'other'],
        ]]];
        $out = UsageExtractor::extract($config, ['count' => $value, 'other' => 9]);
        self::assertSame($expected, $out['tokens']['total']);
        self::assertSame($expected, $out['tokens']['breakdown']['invalid']);
        self::assertSame(9, $out['tokens']['breakdown']['valid']);
    }

    /** @return iterable<string,array{mixed,int}> */
    public static function countValues(): iterable
    {
        yield 'negative integer' => [-9, 0];
        yield 'negative string' => ['-9', 0];
        yield 'negative float' => [-9.0, 0];
        yield 'fraction' => [12.5, 0];
        yield 'fraction string' => ['12.5', 0];
        yield 'string overflow' => [(string) PHP_INT_MAX . '0', 0];
        yield 'exponent overflow' => ['1e999', 0];
        yield 'boolean' => [true, 0];
        yield 'integer' => [12, 12];
        yield 'integer string' => ['12', 12];
        yield 'whole float' => [12.0, 12];
        yield 'decimal string format' => ['12.0', 0];
        yield 'exponent string format' => ['1.2e2', 0];
        yield 'fraction lost by float rounding' => ['1.0000000000000001', 0];
        yield 'multiple signs' => ['++12', 0];
        yield 'null byte' => ["12\0", 0];
        yield 'leading zeros' => ['00012', 12];
        yield 'surrounding whitespace' => [' +12 ', 12];
        yield 'maximum integer' => [PHP_INT_MAX, PHP_INT_MAX];
        yield 'maximum integer string' => [(string) PHP_INT_MAX, PHP_INT_MAX];
        yield 'zero' => [0, 0];
        yield 'zero string' => ['000', 0];
    }

    public function testNonfiniteAndOutOfRangeFloatsAreNotConvertedToCounts(): void
    {
        foreach ([INF, NAN, (float) PHP_INT_MAX + 1.0] as $value) {
            $out = UsageExtractor::extract(['usage' => ['tokens' => ['total' => 'n']]], ['n' => $value]);
            self::assertSame(0, $out['tokens']['total']);
        }
    }
}
