<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\YandexDriver;
use Bblslug\Models\Prompts;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class YandexNativeJsonTest extends TestCase
{
    private const FOUNDATION = [
        'endpoint' => 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion',
        'defaults' => ['model' => 'yandexgpt-5.1'],
        'requirements' => ['headers' => ['Content-Type: application/json']],
    ];

    private const CHAT = [
        'api_type' => 'chat_completions',
        'endpoint' => 'https://ai.api.cloud.yandex.net/v1/chat/completions',
        'defaults' => ['model' => 'gpt://folder/yandexgpt-5.1'],
        'requirements' => ['headers' => ['Content-Type: application/json']],
    ];

    /** @return array<string,mixed> */
    private function body(array $config, string $source, array $options): array
    {
        $request = (new YandexDriver())->buildRequest($config, $source, $options + ['folder_id' => 'b1folder']);
        $request['decoded'] = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        return $request;
    }

    public function testExplicitNativeFoundationUsesRawObjectAndJsonObjectFlag(): void
    {
        $request = $this->body(self::FOUNDATION, '{"title":"Hello"}', [
            'format' => 'json', 'json_transport' => 'native',
        ]);
        self::assertSame('{"title":"Hello"}', $request['decoded']['messages'][1]['text']);
        self::assertTrue($request['decoded']['jsonObject']);
        self::assertArrayNotHasKey('jsonObject', $request['decoded']['completionOptions']);
        self::assertSame(['json_native' => true], $request['responseContext']);
        self::assertStringNotContainsString('‹‹TRANSLATION››', $request['decoded']['messages'][1]['text']);
    }

    public function testExplicitNativeChatUsesRawObjectAndJsonResponseFormat(): void
    {
        $request = $this->body(self::CHAT, '{}', [
            'format' => 'linedJson', 'json_transport' => 'native',
        ]);
        self::assertSame('{}', $request['decoded']['messages'][1]['content']);
        self::assertSame(['type' => 'json_object'], $request['decoded']['response_format']);
        self::assertSame(['json_native' => true], $request['responseContext']);
    }

    /** @dataProvider nativeFormatProvider */
    public function testNativeSupportsBothJsonFormats(string $format): void
    {
        $request = $this->body(self::FOUNDATION, '{}', ['format' => $format, 'json_transport' => 'native']);
        self::assertSame('{}', $request['decoded']['messages'][1]['text']);
    }

    /** @return array<string,array{string}> */
    public static function nativeFormatProvider(): array
    {
        return ['json' => ['json'], 'lined JSON' => ['linedJson']];
    }

    /** @dataProvider invalidNativeFormatProvider */
    public function testNativeRejectsNonJsonFormats(string $format): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new YandexDriver())->buildRequest(self::FOUNDATION, 'Hello', [
            'format' => $format, 'json_transport' => 'native', 'folder_id' => 'b1folder',
        ]);
    }

    /** @return array<string,array{string}> */
    public static function invalidNativeFormatProvider(): array
    {
        return ['text' => ['text'], 'html' => ['html'], 'structured_json' => ['structured_json']];
    }

    /** @dataProvider invalidTransportProvider */
    public function testUnknownTransportIsRejectedForBothJsonFormats(mixed $transport): void
    {
        foreach (['json', 'linedJson'] as $format) {
            try {
                (new YandexDriver())->buildRequest(self::FOUNDATION, '{}', [
                    'format' => $format, 'json_transport' => $transport, 'folder_id' => 'b1folder',
                ]);
                self::fail('Expected invalid json_transport to be rejected');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    /** @return array<string,array{mixed}> */
    public static function invalidTransportProvider(): array
    {
        return ['null' => [null], 'boolean' => [true], 'array' => [[]], 'unknown' => ['other']];
    }

    /** @dataProvider invalidSourceProvider */
    public function testNativeRejectsInvalidOrNonObjectSourceBeforeBuildingHttpRequest(string $source): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new YandexDriver())->buildRequest(self::FOUNDATION, $source, [
            'format' => 'json', 'json_transport' => 'native', 'folder_id' => 'b1folder',
        ]);
    }

    /** @return array<string,array{string}> */
    public static function invalidSourceProvider(): array
    {
        return [
            'array root' => ['[]'],
            'scalar root' => ['"hello"'],
            'number root' => ['1'],
            'malformed' => ['{"title":'],
        ];
    }

    public function testAutoSelectsNativeOnlyWithCapabilityStructuredTemplateAndObjectRoot(): void
    {
        $config = self::FOUNDATION + [
            'capabilities' => ['native_json' => true],
        ];
        $request = $this->body($config, '{"title":"Hello"}', ['format' => 'json']);
        self::assertTrue($request['decoded']['jsonObject']);
        self::assertArrayNotHasKey('jsonObject', $request['decoded']['completionOptions']);
        self::assertSame(['json_native' => true], $request['responseContext']);
    }

    /** @dataProvider autoMarkerConfigProvider */
    public function testAutoPreservesMarkersWhenEligibilityIsMissing(array $config, string $source): void
    {
        $request = $this->body($config, $source, ['format' => 'json']);
        self::assertStringStartsWith("‹‹TRANSLATION››\n", $request['decoded']['messages'][1]['text']);
        self::assertStringEndsWith("\n‹‹END››", $request['decoded']['messages'][1]['text']);
        self::assertSame(['json_native' => false], $request['responseContext']);
        self::assertArrayNotHasKey('jsonObject', $request['decoded']['completionOptions']);
    }

    /** @return array<string,array{array<string,mixed>,string}> */
    public static function autoMarkerConfigProvider(): array
    {
        return [
            'missing capability' => [self::FOUNDATION, '{"title":"Hello"}'],
            'capability false' => [
                self::FOUNDATION + ['capabilities' => ['native_json' => false]], '{"title":"Hello"}',
            ],
            'array root' => [self::FOUNDATION + ['capabilities' => ['native_json' => true]], '[]'],
            'scalar root' => [self::FOUNDATION + ['capabilities' => ['native_json' => true]], '"hello"'],
        ];
    }

    public function testAutoKeepsMarkersForCustomPromptGroupWithoutStructuredTemplate(): void
    {
        $config = self::FOUNDATION + ['capabilities' => ['native_json' => true]];
        $request = $this->body($config, '{}', ['format' => 'json', 'promptKey' => 'experimental_xml']);
        self::assertSame(['json_native' => false], $request['responseContext']);
        self::assertStringStartsWith("‹‹TRANSLATION››\n", $request['decoded']['messages'][1]['text']);
    }

    public function testStructuredJsonRequiresAnExplicitSchema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires response_schema');
        (new YandexDriver())->buildRequest(self::FOUNDATION, '{}', [
            'format' => 'structured_json', 'folder_id' => 'b1folder',
        ]);
    }

    public function testMarkerHtmlContextRemainsRequestLocalAndUnchanged(): void
    {
        $request = $this->body(self::FOUNDATION, '<p>Hello</p>', ['format' => 'html']);
        self::assertSame(['html_fence_free' => true], $request['responseContext']);
        self::assertStringContainsString('‹‹TRANSLATION››', $request['decoded']['messages'][1]['text']);
    }

    /** @dataProvider nativeDocumentProvider */
    public function testNativeParseRequiresStrictJsonObjectWithoutFenceCleanup(string $json): void
    {
        $result = (new YandexDriver())->parseResponse([], $this->response($json), [
            'responseContext' => ['json_native' => true],
        ]);
        self::assertSame($json, $result['text']);
    }

    /** @return array<string,array{string}> */
    public static function nativeDocumentProvider(): array
    {
        return [
            'empty object' => ['{}'],
            'nested object' => ['{"outer":{"quote":"escaped \\"value\\"","lines":"a\\nb"}}'],
            'object with marker strings' => ['{"value":"‹‹TRANSLATION›› x ‹‹END››"}'],
        ];
    }

    /** @dataProvider invalidNativeResponseProvider */
    public function testNativeParseRejectsInvalidOrNonObjectResponse(string $content): void
    {
        $this->expectException(RuntimeException::class);
        (new YandexDriver())->parseResponse([], $this->response($content), [
            'responseContext' => ['json_native' => true],
        ]);
    }

    /** @return array<string,array{string}> */
    public static function invalidNativeResponseProvider(): array
    {
        return [
            'array' => ['[]'], 'scalar' => ['"hello"'], 'malformed' => ['{"x":'],
            'fenced' => ["```json\n{}\n```"],
        ];
    }

    public function testExplicitNativeCanParseWithoutContextAndContextOverridesConflictingFormat(): void
    {
        self::assertSame('{}', (new YandexDriver())->parseResponse([], $this->response('{}'), [
            'json_transport' => 'native', 'format' => 'json',
        ])['text']);
        $this->expectException(RuntimeException::class);
        (new YandexDriver())->parseResponse([], $this->response('{}'), [
            'format' => 'json', 'responseContext' => ['json_native' => false],
        ]);
    }

    public function testBuiltMarkerContextWinsOverNativeCapabilityAtParseTime(): void
    {
        $request = $this->body(self::FOUNDATION + ['capabilities' => ['native_json' => true]], '[]', [
            'format' => 'json', 'json_transport' => 'markers',
        ]);
        self::assertSame(['json_native' => false], $request['responseContext']);
        $this->expectException(RuntimeException::class);
        (new YandexDriver())->parseResponse([], $this->response('{}'), [
            'format' => 'json',
            'json_transport' => 'native',
            'responseContext' => $request['responseContext'],
        ]);
    }

    public function testNativeAndMarkerChatUsageIsPreserved(): void
    {
        $native = [
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{}']]],
            'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 1],
        ];
        self::assertSame(
            ['prompt_tokens' => 2, 'completion_tokens' => 1],
            (new YandexDriver())->parseResponse(
                [],
                json_encode($native, JSON_THROW_ON_ERROR),
                ['responseContext' => ['json_native' => true]]
            )['usage']
        );

        $marker = [
            'choices' => [['finish_reason' => 'stop', 'message' => [
                'content' => "‹‹TRANSLATION››\n{}\n‹‹END››",
            ]]],
            'usage' => ['prompt_tokens' => 3],
        ];
        self::assertSame(
            ['prompt_tokens' => 3],
            (new YandexDriver())->parseResponse([], json_encode($marker, JSON_THROW_ON_ERROR), [])['usage']
        );
    }

    /** @dataProvider responseShapeProvider */
    public function testNativeResponseShapesAndCompletionStatusesAreCheckedBeforeParsing(array $response): void
    {
        $result = (new YandexDriver())->parseResponse(
            [],
            json_encode($response, JSON_THROW_ON_ERROR),
            ['responseContext' => ['json_native' => true]]
        );
        self::assertSame('{}', $result['text']);
    }

    /** @return array<string,array{array<string,mixed>}> */
    public static function responseShapeProvider(): array
    {
        $foundation = ['result' => ['alternatives' => [[
            'status' => 'ALTERNATIVE_STATUS_FINAL', 'message' => ['text' => '{}'],
        ]]]];
        $chat = ['choices' => [[
            'finish_reason' => 'stop', 'message' => ['content' => '{}'],
        ]]];
        $legacy = ['completions' => [['finish_reason' => 'stop', 'text' => '{}']]];

        return [
            'foundation' => [$foundation], 'chat' => [$chat], 'legacy' => [$legacy],
        ];
    }

    /** @dataProvider rejectedStatusProvider */
    public function testNativeRejectsTruncationFilterRefusalAndOtherStatuses(array $response): void
    {
        $this->expectException(RuntimeException::class);
        (new YandexDriver())->parseResponse([], json_encode($response, JSON_THROW_ON_ERROR), [
            'responseContext' => ['json_native' => true],
        ]);
    }

    /** @return array<string,array{array<string,mixed>}> */
    public static function rejectedStatusProvider(): array
    {
        $truncated = ['result' => ['alternatives' => [[
            'status' => 'ALTERNATIVE_STATUS_TRUNCATED_FINAL', 'message' => ['text' => '{}'],
        ]]]];
        $filter = ['result' => ['alternatives' => [[
            'status' => 'ALTERNATIVE_STATUS_CONTENT_FILTER', 'message' => ['text' => '{}'],
        ]]]];
        $refusal = ['choices' => [[
            'finish_reason' => 'stop', 'message' => ['refusal' => 'no', 'content' => '{}'],
        ]]];
        $other = ['completions' => [['finish_reason' => 'length', 'text' => '{}']]];

        return [
            'foundation truncated' => [$truncated], 'foundation filter' => [$filter],
            'chat refusal' => [$refusal], 'legacy other' => [$other],
        ];
    }

    private function response(string $content): string
    {
        return json_encode(['result' => ['alternatives' => [[
            'status' => 'ALTERNATIVE_STATUS_FINAL', 'message' => ['text' => $content],
        ]]]], JSON_THROW_ON_ERROR);
    }
}
