<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use PHPUnit\Framework\TestCase;
use Bblslug\Models\Drivers\OpenAiDriver;
use Bblslug\Models\Drivers\AnthropicDriver;
use Bblslug\Models\Drivers\GoogleDriver;
use Bblslug\Models\Drivers\XaiDriver;
use Bblslug\Models\Drivers\YandexDriver;
use RuntimeException;

/**
 * Common parseResponse contract tests across drivers that use START/END markers:
 * - Extract text between START/END markers
 * - Surface truncation/finish_reason errors (where supported)
 * - Throw when markers are missing
 *
 * DeepL is intentionally excluded here because it does NOT use markers.
 */
final class DriverParseCommonTest extends TestCase
{
    /**
     * @return array<string, array{0: object, 1: string, 2: string}>
     */
    public function driversAndValidPayloads(): array
    {
        return [
            'openai' => [
                new OpenAiDriver(),
                json_encode([
                    'choices' => [[
                        'message' => ['content' => 'foo ‹‹TRANSLATION››Hello‹‹END›› bar'],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['prompt_tokens' => 1],
                ], JSON_UNESCAPED_UNICODE),
                'Hello',
            ],
            'anthropic' => [
                new AnthropicDriver(),
                json_encode([
                    'choices' => [[
                        'message' => ['content' => '‹‹TRANSLATION››Hi there‹‹END››'],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['input_tokens' => 1],
                ], JSON_UNESCAPED_UNICODE),
                'Hi there',
            ],
            'google' => [
                new GoogleDriver(),
                json_encode([
                    'candidates' => [[
                        'finishReason' => 'STOP',
                        'content' => ['parts' => [
                            ['text' => 'pfx ‹‹TRANSLATION››Bonjour‹‹END›› sfx'],
                        ]],
                    ]],
                    'usageMetadata' => ['totalTokenCount' => 1],
                ], JSON_UNESCAPED_UNICODE),
                'Bonjour',
            ],
            'xai' => [
                new XaiDriver(),
                json_encode([
                    'choices' => [[
                        'message' => ['content' => '‹‹TRANSLATION››Ciao‹‹END››'],
                    ]],
                    'usage' => ['prompt_tokens' => 1],
                ], JSON_UNESCAPED_UNICODE),
                'Ciao',
            ],
            'yandex' => [
                new YandexDriver(),
                json_encode([
                    'result' => [
                        'alternatives' => [[
                            'message' => ['text' => 'xx ‹‹TRANSLATION››Privet‹‹END›› yy'],
                        ]],
                        'usage' => ['inputTextTokens' => 1],
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'Privet',
            ],
        ];
    }

    /**
     * @dataProvider driversAndValidPayloads
     */
    public function testParsesTextBetweenMarkers(object $driver, string $json, string $expected): void
    {
        $res = $driver->parseResponse([], $json);

        $this->assertArrayHasKey('text', $res);
        $this->assertArrayHasKey('usage', $res);
        $this->assertNotSame('', $res['text']);
        $this->assertSame($expected, $res['text']);
    }

    /**
     * @return array<string, array{0: object, 1: string}>
     */
    public function driversAndMissingMarkers(): array
    {
        return [
            'openai' => [
                new OpenAiDriver(),
                json_encode([
                    'choices' => [[
                        'message' => ['content' => 'no markers here'],
                        'finish_reason' => 'stop',
                    ]],
                ], JSON_UNESCAPED_UNICODE),
            ],
            'anthropic' => [
                new AnthropicDriver(),
                json_encode([
                    'choices' => [[
                        'message' => ['content' => 'just text'],
                        'finish_reason' => 'stop',
                    ]],
                ], JSON_UNESCAPED_UNICODE),
            ],
            'google' => [
                new GoogleDriver(),
                json_encode([
                    'candidates' => [[
                        'finishReason' => 'STOP',
                        'content' => ['parts' => [
                            ['text' => 'plain text without markers'],
                        ]],
                    ]],
                ], JSON_UNESCAPED_UNICODE),
            ],
            'xai' => [
                new XaiDriver(),
                json_encode([
                    'choices' => [[
                        'message' => ['content' => 'missing'],
                    ]],
                ], JSON_UNESCAPED_UNICODE),
            ],
            'yandex' => [
                new YandexDriver(),
                json_encode([
                    'result' => [
                        'alternatives' => [[
                            'message' => ['text' => 'still no markers'],
                        ]],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    /**
     * @dataProvider driversAndMissingMarkers
     */
    public function testThrowsOnMissingMarkers(object $driver, string $json): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Markers not found|translation failed/i');
        $driver->parseResponse([], $json);
    }

    /**
     * @return array<string, array{0: object, 1: string}>
     */
    public function driversWithTruncationPayloads(): array
    {
        return [
            // OpenAI signals truncation via finish_reason = "length"
            'openai-truncated' => [
                new OpenAiDriver(),
                json_encode([
                    'choices' => [[
                        'message' => ['content' => '‹‹TRANSLATION››partial‹‹END››'],
                        'finish_reason' => 'length',
                    ]],
                ], JSON_UNESCAPED_UNICODE),
            ],
            // Anthropic uses the same finish_reason field in our wrapper
            'anthropic-truncated' => [
                new AnthropicDriver(),
                json_encode([
                    'choices' => [[
                        'message' => ['content' => '‹‹TRANSLATION››partial‹‹END››'],
                        'finish_reason' => 'length',
                    ]],
                ], JSON_UNESCAPED_UNICODE),
            ],
            // Google uses finishReason = "MAX_TOKENS"
            'google-truncated' => [
                new GoogleDriver(),
                json_encode([
                    'candidates' => [[
                        'finishReason' => 'MAX_TOKENS',
                        'content' => ['parts' => [
                            ['text' => '‹‹TRANSLATION››partial‹‹END››'],
                        ]],
                    ]],
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    /**
     * @dataProvider driversWithTruncationPayloads
     */
    public function testTruncationIsSurfacedAsError(object $driver, string $json): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/truncated|MAX_TOKENS|length/i');
        $driver->parseResponse([], $json);
    }
}
