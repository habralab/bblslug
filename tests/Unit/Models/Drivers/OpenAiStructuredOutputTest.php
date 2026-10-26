<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\OpenAiDriver;
use PHPUnit\Framework\TestCase;

final class OpenAiStructuredOutputTest extends TestCase
{
    /** @return array<string,mixed> */
    private function schema(): array
    {
        return ['type' => 'object', 'properties' => ['translation' => ['type' => 'string']], 'required' => ['translation'], 'additionalProperties' => false];
    }

    /** @return array<string,mixed> */
    private function config(string $apiType = 'chat_completions'): array
    {
        return ['endpoint' => 'https://api.openai.com/v1/' . ($apiType === 'responses' ? 'responses' : 'chat/completions'), 'api_type' => $apiType, 'defaults' => ['model' => 'gpt-5.1']];
    }

    public function testChatCompletionsUsesNativeFormatAndRawInput(): void
    {
        $options = ['format' => 'json', 'response_schema' => $this->schema(), 'response_schema_name' => 'translation_result', 'strict' => false];
        $body = json_decode((new OpenAiDriver())->buildRequest($this->config(), '{"translation":"Hola"}', $options)['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($options['response_schema'], $body['response_format']['json_schema']['schema']);
        $this->assertSame(['type' => 'json_schema', 'json_schema' => ['name' => 'translation_result', 'strict' => false, 'schema' => $this->schema()]], $body['response_format']);
        $this->assertSame('{"translation":"Hola"}', $body['messages'][1]['content']);
        $this->assertStringNotContainsString('‹‹TRANSLATION››', $body['messages'][0]['content']);
    }

    public function testResponsesUsesTextFormatAndRawNativeParser(): void
    {
        $options = ['format' => 'structured_json', 'response_schema' => $this->schema()];
        $driver = new OpenAiDriver();
        $body = json_decode($driver->buildRequest($this->config('responses'), 'input', $options)['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['type' => 'json_schema', 'name' => 'anonymous', 'strict' => true, 'schema' => $this->schema()], $body['text']['format']);
        $this->assertArrayNotHasKey('response_format', $body);
        $parsed = $driver->parseResponse($this->config('responses'), json_encode(['output_text' => " {\"translation\":\"Hola\"} ", 'usage' => ['total_tokens' => 4]], JSON_THROW_ON_ERROR), $options);
        $this->assertSame('{"translation":"Hola"}', $parsed['text']);
        $this->assertSame(['total_tokens' => 4], $parsed['usage']);
    }

    public function testNativeParserHandlesChatStructuredContent(): void
    {
        $result = (new OpenAiDriver())->parseResponse($this->config(), json_encode(['choices' => [['message' => ['content' => [['type' => 'text', 'text' => "\n{\"translation\":\"Hola\"}\n"]]]]]], JSON_THROW_ON_ERROR), ['response_schema' => $this->schema()]);
        $this->assertSame('{"translation":"Hola"}', $result['text']);
    }

    public function testDefaultSchemaSelectsNativeParserWithoutPerRequestSchema(): void
    {
        $config = $this->config();
        $config['defaults']['format'] = 'json';
        $config['defaults']['response_schema'] = $this->schema();
        $driver = new OpenAiDriver();

        $body = json_decode($driver->buildRequest($config, '{}', [])['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('response_format', $body);
        $parsed = $driver->parseResponse($config, json_encode([
            'choices' => [['message' => ['content' => '{"translation":"Hola"}']]],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame('{"translation":"Hola"}', $parsed['text']);
    }

    public function testExplicitFormatOverrideUsesMarkersWhenNoDefaultSchemaExists(): void
    {
        $config = $this->config();
        $config['defaults']['format'] = 'json';
        $driver = new OpenAiDriver();
        $parsed = $driver->parseResponse($config, json_encode([
            'choices' => [['message' => ['content' => "‹‹TRANSLATION››Hola‹‹END››"]]],
        ], JSON_THROW_ON_ERROR), ['format' => 'text']);
        $this->assertSame('Hola', $parsed['text']);
    }

    /** @dataProvider nativeFormatProvider */
    public function testDefaultSchemaSelectsNativeParserForJsonFormats(string $format): void
    {
        $config = $this->config();
        $config['defaults']['format'] = $format;
        $config['defaults']['response_schema'] = $this->schema();
        $parsed = (new OpenAiDriver())->parseResponse($config, json_encode([
            'choices' => [['message' => ['content' => '{"translation":"Hola"}']]],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame('{"translation":"Hola"}', $parsed['text']);
    }

    /** @return array<string,array{string}> */
    public static function nativeFormatProvider(): array
    {
        return ['json' => ['json'], 'linedJson' => ['linedJson'], 'structured_json' => ['structured_json']];
    }

    /** @dataProvider invalidBuildOptions */
    public function testInvalidNativeOptionsAreRejected(array $options, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new OpenAiDriver())->buildRequest($this->config(), 'input', $options);
    }

    /** @return array<string,array{array<string,mixed>,string}> */
    public function invalidBuildOptions(): array
    {
        return [
            'missing structured schema' => [['format' => 'structured_json'], 'requires response_schema'],
            'empty schema' => [['format' => 'json', 'response_schema' => []], 'non-empty associative'],
            'list schema' => [['format' => 'json', 'response_schema' => ['type']], 'non-empty associative'],
            'bad schema name' => [['format' => 'json', 'response_schema' => $this->schema(), 'response_schema_name' => 'bad name'], 'response_schema_name'],
            'bad strict' => [['format' => 'json', 'response_schema' => $this->schema(), 'strict' => 'true'], 'strict must'],
        ];
    }

    public function testLegacyCompletionsRejectsNativeSchema(): void
    {
        $this->expectExceptionMessage('legacy completions');
        (new OpenAiDriver())->buildRequest($this->config('completions'), 'input', ['format' => 'json', 'response_schema' => $this->schema()]);
    }

    public function testNormalFormatStillUsesMarkers(): void
    {
        $body = json_decode((new OpenAiDriver())->buildRequest($this->config(), 'input', ['format' => 'json'])['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('‹‹TRANSLATION››', $body['messages'][1]['content']);
        $this->assertArrayNotHasKey('response_format', $body);
        $result = (new OpenAiDriver())->parseResponse($this->config(), json_encode(['choices' => [['message' => ['content' => "‹‹TRANSLATION››\nHola\n‹‹END››"]]]], JSON_THROW_ON_ERROR));
        $this->assertSame('Hola', $result['text']);
    }

    public function testNativeRefusalAndTruncationRemainErrors(): void
    {
        $driver = new OpenAiDriver();
        try {
            $driver->parseResponse($this->config(), json_encode(['choices' => [['message' => ['refusal' => 'policy']]]], JSON_THROW_ON_ERROR), ['response_schema' => $this->schema()]);
            $this->fail('Expected refusal');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('refused: policy', $e->getMessage());
        }
        $this->expectExceptionMessage('truncated');
        $driver->parseResponse($this->config(), json_encode(['choices' => [['finish_reason' => 'length', 'message' => ['content' => '{']]]], JSON_THROW_ON_ERROR), ['response_schema' => $this->schema()]);
    }
}
