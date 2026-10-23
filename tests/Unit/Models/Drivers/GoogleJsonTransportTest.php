<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\GoogleDriver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GoogleJsonTransportTest extends TestCase
{
    private const CONFIG = ['endpoint' => 'https://example.test/generate'];

    /** @return array<string,mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['title' => ['type' => 'string']],
            'required' => ['title'],
            'additionalProperties' => false,
        ];
    }

    /** @return array<string,mixed> */
    private function body(string $text, array $options): array
    {
        $request = (new GoogleDriver())->buildRequest(self::CONFIG, $text, $options);
        return json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
    }

    public function testExplicitNativeJsonUsesRawInputAndJsonMimeWithoutSchema(): void
    {
        $body = $this->body('{"title":"Hello"}', ['format' => 'json', 'json_transport' => 'native']);

        self::assertSame('{"title":"Hello"}', $body['contents'][0]['parts'][0]['text']);
        self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
        self::assertArrayNotHasKey('responseJsonSchema', $body['generationConfig']);
        self::assertStringNotContainsString(
            '‹‹TRANSLATION››',
            $body['system_instruction']['parts'][0]['text']
        );
        self::assertStringNotContainsString('‹‹TRANSLATION››', $body['contents'][0]['parts'][0]['text']);
    }

    /** @dataProvider nativeFormatProvider */
    public function testExplicitNativeTransportSupportsJsonFormats(string $format): void
    {
        $body = $this->body('{}', ['format' => $format, 'json_transport' => 'native']);

        self::assertSame('{}', $body['contents'][0]['parts'][0]['text']);
        self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
    }

    /** @return array<string,array{string}> */
    public static function nativeFormatProvider(): array
    {
        return ['json' => ['json'], 'lined JSON' => ['linedJson']];
    }

    public function testStructuredJsonStillRequiresSchema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GoogleDriver())->buildRequest(self::CONFIG, '{}', ['format' => 'structured_json']);
    }

    public function testStructuredJsonWithSchemaUsesNativeRawInputAndStructuredPrompt(): void
    {
        $schema = $this->schema();
        $body = $this->body('{"title":"Hello"}', ['format' => 'structured_json', 'response_schema' => $schema]);

        self::assertSame('{"title":"Hello"}', $body['contents'][0]['parts'][0]['text']);
        self::assertSame($schema, $body['generationConfig']['responseJsonSchema']);
        self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
        self::assertStringNotContainsString('‹‹TRANSLATION››', $body['system_instruction']['parts'][0]['text']);
    }

    public function testSchemaAutomaticallySelectsNativeJsonTransport(): void
    {
        $body = $this->body('{"title":"Hello"}', ['format' => 'json', 'response_schema' => $this->schema()]);

        self::assertSame('{"title":"Hello"}', $body['contents'][0]['parts'][0]['text']);
        self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
    }

    public function testDefaultAndExplicitMarkerTransportKeepMarkerProtocol(): void
    {
        foreach ([['format' => 'json'], ['format' => 'json', 'json_transport' => 'markers']] as $options) {
            $body = $this->body('{}', $options);
            $content = $body['contents'][0]['parts'][0]['text'];
            self::assertStringStartsWith("‹‹TRANSLATION››\n", $content);
            self::assertStringEndsWith("\n‹‹END››", $content);
            self::assertArrayNotHasKey('responseMimeType', $body['generationConfig']);
        }
    }

    public function testSchemaAndExplicitMarkerTransportConflict(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GoogleDriver())->buildRequest(self::CONFIG, '{}', [
            'format' => 'json', 'json_transport' => 'markers', 'response_schema' => $this->schema(),
        ]);
    }

    /** @dataProvider invalidTransportProvider */
    public function testInvalidTransportIsRejected(mixed $transport): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GoogleDriver())->buildRequest(self::CONFIG, '{}', ['format' => 'json', 'json_transport' => $transport]);
    }

    /** @return array<string,array{mixed}> */
    public static function invalidTransportProvider(): array
    {
        return ['null' => [null], 'boolean' => [true], 'unknown' => ['other'], 'array' => [[]]];
    }

    /** @dataProvider nativeOnlyFormatProvider */
    public function testNativeTransportIsRejectedForTextAndHtml(string $format): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GoogleDriver())->buildRequest(self::CONFIG, 'Hello', ['format' => $format, 'json_transport' => 'native']);
    }

    /** @return array<string,array{string}> */
    public static function nativeOnlyFormatProvider(): array
    {
        return ['text' => ['text'], 'html' => ['html']];
    }

    public function testNativeParseReturnsBareJsonAndIgnoresThoughtParts(): void
    {
        $response = json_encode([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [
                    ['text' => '{"title":'], ['text' => 'hidden', 'thought' => true], ['text' => '"Hallo"}'],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        self::assertSame('{"title":"Hallo"}', (new GoogleDriver())->parseResponse(
            [],
            $response,
            ['json_transport' => 'native']
        )['text']);
    }

    /** @dataProvider rejectedFinishReasonProvider */
    public function testNativeParseRejectsNonStopFinishReasons(string $reason): void
    {
        $response = json_encode([
            'candidates' => [[
                'finishReason' => $reason, 'content' => ['parts' => [['text' => '{}']]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(RuntimeException::class);
        (new GoogleDriver())->parseResponse([], $response, ['json_transport' => 'native']);
    }

    /** @return array<string,array{string}> */
    public static function rejectedFinishReasonProvider(): array
    {
        return ['truncated' => ['MAX_TOKENS'], 'safety' => ['SAFETY']];
    }

    public function testMarkerParseStillRejectsMissingMarkers(): void
    {
        $response = json_encode([
            'candidates' => [[
                'finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{}']]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(RuntimeException::class);
        (new GoogleDriver())->parseResponse([], $response);
    }
}
