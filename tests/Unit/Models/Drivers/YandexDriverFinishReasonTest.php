<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\YandexDriver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class YandexDriverFinishReasonTest extends TestCase
{
    private const MARKED = "‹‹TRANSLATION››\nHallo\n‹‹END››";

    /** @dataProvider foundationRejectedStatusProvider */
    public function testFoundationRejectsNonFinalAlternativeStatus(mixed $status): void
    {
        $body = json_encode([
            'result' => [
                'alternatives' => [
                    ['message' => ['text' => self::MARKED], 'status' => $status],
                    ['message' => ['text' => self::MARKED], 'status' => 'ALTERNATIVE_STATUS_FINAL'],
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Yandex: (?:translation|a tool call)/i');
        (new YandexDriver())->parseResponse([], $body);
    }

    /** @return array<string,array{mixed}> */
    public static function foundationRejectedStatusProvider(): array
    {
        return [
            'partial' => ['ALTERNATIVE_STATUS_PARTIAL'],
            'truncated' => ['ALTERNATIVE_STATUS_TRUNCATED_FINAL'],
            'content filter' => ['ALTERNATIVE_STATUS_CONTENT_FILTER'],
            'tool calls' => ['ALTERNATIVE_STATUS_TOOL_CALLS'],
            'unknown' => ['ALTERNATIVE_STATUS_UNKNOWN'],
            'null' => [null],
            'non-string' => [123],
        ];
    }

    /** @dataProvider chatRejectedFinishReasonProvider */
    public function testChatCompletionsRejectsNonStopFinishReason(mixed $reason): void
    {
        $body = json_encode([
            'choices' => [
                ['message' => ['content' => self::MARKED], 'finish_reason' => $reason],
                ['message' => ['content' => self::MARKED], 'finish_reason' => 'stop'],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Yandex: (?:translation|a tool call)/i');
        (new YandexDriver())->parseResponse([], $body);
    }

    /** @return array<string,array{mixed}> */
    public static function chatRejectedFinishReasonProvider(): array
    {
        return [
            'length' => ['length'],
            'content filter' => ['content_filter'],
            'tool calls' => ['tool_calls'],
            'function call' => ['function_call'],
            'unknown' => ['other'],
            'null' => [null],
            'non-string' => [123],
        ];
    }

    /** @dataProvider legacyRejectedFinishReasonProvider */
    public function testLegacyCompletionsRejectNonStopFinishReason(mixed $reason): void
    {
        $body = json_encode([
            'completions' => [
                ['text' => self::MARKED, 'finish_reason' => $reason],
                ['text' => self::MARKED, 'finish_reason' => 'stop'],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Yandex: (?:translation|a tool call)/i');
        (new YandexDriver())->parseResponse([], $body);
    }

    /** @return array<string,array{mixed}> */
    public static function legacyRejectedFinishReasonProvider(): array
    {
        return [
            'length' => ['length'],
            'content filter' => ['content_filter'],
            'tool calls' => ['tool_calls'],
            'function call' => ['function_call'],
            'unknown' => ['other'],
            'null' => [null],
            'non-string' => [123],
        ];
    }

    public function testChatRefusalIsRejectedEvenWhenContentHasMarkers(): void
    {
        $body = json_encode([
            'choices' => [[
                'message' => ['content' => self::MARKED, 'refusal' => 'I cannot translate this'],
                'finish_reason' => 'stop',
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/refused/i');
        (new YandexDriver())->parseResponse([], $body);
    }

    public function testMissingMetadataRemainsCompatibleAndUsageIsPreserved(): void
    {
        $body = json_encode([
            'result' => [
                'alternatives' => [['message' => ['text' => self::MARKED]]],
                'usage' => ['totalTokens' => '7'],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $result = (new YandexDriver())->parseResponse([], $body);
        self::assertSame('Hallo', $result['text']);
        self::assertSame(['totalTokens' => '7'], $result['usage']);
    }

    public function testFinalMetadataIsAcceptedForFoundationChatAndLegacy(): void
    {
        $driver = new YandexDriver();
        $foundation = $driver->parseResponse([], json_encode([
                'result' => [
                    'alternatives' => [[
                        'message' => ['text' => self::MARKED],
                        'status' => 'ALTERNATIVE_STATUS_FINAL',
                    ]],
                ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $chat = $driver->parseResponse([], json_encode([
            'choices' => [['message' => ['content' => self::MARKED], 'finish_reason' => 'stop']],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $legacy = $driver->parseResponse([], json_encode([
            'completions' => [['text' => self::MARKED, 'finish_reason' => 'stop']],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        self::assertSame('Hallo', $foundation['text']);
        self::assertSame('Hallo', $chat['text']);
        self::assertSame('Hallo', $legacy['text']);
    }
}
