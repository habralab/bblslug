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
}
