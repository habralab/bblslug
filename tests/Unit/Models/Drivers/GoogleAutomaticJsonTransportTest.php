<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\GoogleDriver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GoogleAutomaticJsonTransportTest extends TestCase
{
    private const CONFIG = [
        'endpoint' => 'https://example.test/gemini-2.5-flash:generateContent',
        'model' => 'gemini-2.5-flash',
        'capabilities' => ['native_json' => true],
    ];

    /** @return array<string,mixed> */
    private function body(array $config, string $text, array $options): array
    {
        $request = (new GoogleDriver())->buildRequest($config, $text, $options);
        return json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string,mixed> */
    private function nativeResponse(string $text, string $finishReason = 'STOP'): array
    {
        return [
            'candidates' => [[
                'finishReason' => $finishReason,
                'content' => ['parts' => [['text' => $text]]],
            ]],
        ];
    }

    public function testEligibleGeminiUsesNativeTransportForJsonAndLinedJsonByDefault(): void
    {
        foreach (['json', 'linedJson'] as $format) {
            $body = $this->body(self::CONFIG, '{"title":"Hello"}', ['format' => $format]);

            self::assertSame('{"title":"Hello"}', $body['contents'][0]['parts'][0]['text']);
            self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
            self::assertStringNotContainsString('‹‹TRANSLATION››', $body['contents'][0]['parts'][0]['text']);
        }
    }

    public function testAutoIsAlsoAcceptedExplicitly(): void
    {
        $body = $this->body(self::CONFIG, '{}', ['format' => 'json', 'json_transport' => 'auto']);

        self::assertSame('{}', $body['contents'][0]['parts'][0]['text']);
        self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
    }

    /** @dataProvider nonEligibleConfigProvider */
    public function testAutoDoesNotGuessFromModelOrEndpoint(array $config): void
    {
        $body = $this->body($config, '{}', ['format' => 'json']);
        $content = $body['contents'][0]['parts'][0]['text'];

        self::assertStringStartsWith("‹‹TRANSLATION››\n", $content);
        self::assertStringEndsWith("\n‹‹END››", $content);
        self::assertArrayNotHasKey('responseMimeType', $body['generationConfig']);
    }

    /** @return array<string,array{array<string,mixed>}> */
    public static function nonEligibleConfigProvider(): array
    {
        return [
            'missing capability despite Gemini model' => [[
                'endpoint' => 'https://example.test/gemini-2.5-flash:generateContent',
                'model' => 'gemini-2.5-flash',
            ]],
            'false capability' => [[
                'endpoint' => 'https://example.test/gemini-2.5-flash:generateContent',
                'capabilities' => ['native_json' => false],
            ]],
            'Gemini-looking endpoint' => [[
                'endpoint' => 'https://example.test/models/gemini-2.5-flash:generateContent',
            ]],
        ];
    }

    /** @dataProvider nonJsonFormatProvider */
    public function testAutoAlwaysUsesMarkersForTextAndHtml(string $format): void
    {
        $body = $this->body(self::CONFIG, 'Hello', ['format' => $format]);
        $content = $body['contents'][0]['parts'][0]['text'];

        self::assertStringStartsWith("‹‹TRANSLATION››\n", $content);
        self::assertStringEndsWith("\n‹‹END››", $content);
        self::assertArrayNotHasKey('responseMimeType', $body['generationConfig']);
    }

    /** @return array<string,array{string}> */
    public static function nonJsonFormatProvider(): array
    {
        return ['text' => ['text'], 'html' => ['html']];
    }

    public function testCustomPromptUsesMarkersInAutoButExplicitNativeRequestsStructuredTemplate(): void
    {
        $body = $this->body(self::CONFIG, '{}', [
            'format' => 'json',
            'promptKey' => 'experimental_xml',
        ]);
        self::assertStringStartsWith("‹‹TRANSLATION››\n", $body['contents'][0]['parts'][0]['text']);
        self::assertArrayNotHasKey('responseMimeType', $body['generationConfig']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Prompt 'experimental_xml.structured_json' not found");
        (new GoogleDriver())->buildRequest(self::CONFIG, '{}', [
            'format' => 'json',
            'promptKey' => 'experimental_xml',
            'json_transport' => 'native',
        ]);
    }

    public function testCustomTranslatorWithoutNativeTemplateKeepsMarkers(): void
    {
        $property = (new \ReflectionClass(\Bblslug\Models\Prompts::class))->getProperty('templates');
        $previous = $property->getValue();
        $path = tempnam(sys_get_temp_dir(), 'google-custom-prompt-');
        try {
            file_put_contents($path, "translator:\n  json: 'Custom JSON {start} {end} {source} {target} {context}'\n");
            $property->setValue(null, null);
            \Bblslug\Models\Prompts::load($path);
            $body = $this->body(self::CONFIG, '{}', ['format' => 'json']);
            self::assertStringStartsWith('Custom JSON', $body['system_instruction']['parts'][0]['text']);
            self::assertArrayNotHasKey('responseMimeType', $body['generationConfig']);
            self::assertStringStartsWith("‹‹TRANSLATION››\n", $body['contents'][0]['parts'][0]['text']);
        } finally {
            $property->setValue(null, $previous);
            unlink($path);
        }
    }

    public function testResponseSchemaSelectsNativeTransportInAuto(): void
    {
        $body = $this->body(self::CONFIG, '{"title":"Hello"}', [
            'format' => 'json',
            'response_schema' => ['type' => 'object'],
        ]);

        self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
        self::assertSame(['type' => 'object'], $body['generationConfig']['responseJsonSchema']);
    }

    public function testBuildAndParseUseTheSameAutomaticDefault(): void
    {
        $options = ['format' => 'linedJson'];
        $body = $this->body(self::CONFIG, '{}', $options);
        self::assertSame('{}', $body['contents'][0]['parts'][0]['text']);

        $parsed = (new GoogleDriver())->parseResponse(
            self::CONFIG,
            json_encode($this->nativeResponse('{"title":"Hallo"}'), JSON_THROW_ON_ERROR),
            $options
        );
        self::assertSame('{"title":"Hallo"}', $parsed['text']);
    }

    /** @dataProvider rejectedAutomaticFinishReasonProvider */
    public function testAutomaticNativeParseStillRejectsReferenceAndTruncation(string $reason): void
    {
        $this->expectException(RuntimeException::class);
        (new GoogleDriver())->parseResponse(
            self::CONFIG,
            json_encode($this->nativeResponse('{}', $reason), JSON_THROW_ON_ERROR),
            ['format' => 'json']
        );
    }

    /** @return array<string,array{string}> */
    public static function rejectedAutomaticFinishReasonProvider(): array
    {
        return [
            'reference stop is rejected' => ['OTHER'],
            'truncated' => ['MAX_TOKENS'],
        ];
    }

    public function testExplicitMarkersOverrideAutomaticCapability(): void
    {
        $body = $this->body(self::CONFIG, '{}', [
            'format' => 'json',
            'json_transport' => 'markers',
        ]);
        self::assertStringStartsWith("‹‹TRANSLATION››\n", $body['contents'][0]['parts'][0]['text']);
        self::assertArrayNotHasKey('responseMimeType', $body['generationConfig']);
    }

    public function testExplicitNativeRemainsAvailableWithoutCapability(): void
    {
        $body = $this->body(
            ['endpoint' => 'https://example.test/generate'],
            '{}',
            ['format' => 'json', 'json_transport' => 'native']
        );
        self::assertSame('{}', $body['contents'][0]['parts'][0]['text']);
        self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
    }

    public function testSchemaAndExplicitMarkersStillConflict(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GoogleDriver())->buildRequest(self::CONFIG, '{}', [
            'format' => 'json',
            'json_transport' => 'markers',
            'response_schema' => ['type' => 'object'],
        ]);
    }
}
