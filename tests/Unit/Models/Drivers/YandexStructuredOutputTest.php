<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\YandexDriver;
use PHPUnit\Framework\TestCase;

final class YandexStructuredOutputTest extends TestCase
{
    private const CONFIG = ['defaults' => ['model' => 'yandexgpt']];
    private const SCHEMA = ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]];

    public function testFoundationSchemaUsesNativePayloadAndContext(): void
    {
        $request = (new YandexDriver())->buildRequest(self::CONFIG, '{"title":"Hello"}', [
            'folder_id' => 'folder', 'format' => 'structured_json', 'response_schema' => self::SCHEMA,
        ]);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['schema' => self::SCHEMA], $body['jsonSchema']);
        self::assertArrayNotHasKey('jsonObject', $body);
        self::assertSame('{"title":"Hello"}', $body['messages'][1]['text']);
        self::assertStringNotContainsString('‹‹TRANSLATION››', $body['messages'][0]['text']);
        self::assertSame(['json_native' => true], $request['responseContext']);
        self::assertSame('{"title":"Hola"}', (new YandexDriver())->parseResponse([], $this->response(), [
            'responseContext' => $request['responseContext'],
        ])['text']);
    }

    public function testChatSchemaUsesDocumentedEnvelope(): void
    {
        $request = (new YandexDriver())->buildRequest(self::CONFIG + ['api_type' => 'chat_completions'], '{}', [
            'folder_id' => 'folder', 'format' => 'json', 'response_schema' => self::SCHEMA,
            'response_schema_name' => 'translation', 'strict' => false,
        ]);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['type' => 'json_schema', 'json_schema' => [
            'name' => 'translation', 'schema' => self::SCHEMA, 'strict' => false,
        ]], $body['response_format']);
        self::assertSame('{}', $body['messages'][1]['content']);
    }

    /** @dataProvider invalidOptions */
    public function testInvalidSchemaOptionsAreRejected(array $options): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new YandexDriver())->buildRequest(self::CONFIG, '{}', $options + ['folder_id' => 'folder']);
    }

    public static function invalidOptions(): array
    {
        return [
            'missing' => [['format' => 'structured_json']],
            'empty' => [['format' => 'json', 'response_schema' => []]],
            'list' => [['format' => 'json', 'response_schema' => ['object']]],
            'scalar' => [['format' => 'json', 'response_schema' => 'object']],
            'format' => [['format' => 'text', 'response_schema' => self::SCHEMA]],
            'markers' => [['format' => 'json', 'response_schema' => self::SCHEMA, 'json_transport' => 'markers']],
            'name' => [['format' => 'json', 'response_schema' => self::SCHEMA, 'response_schema_name' => 'bad name']],
            'strict' => [['format' => 'json', 'response_schema' => self::SCHEMA, 'strict' => 'yes']],
            'source' => [['format' => 'json', 'response_schema' => self::SCHEMA, 'json_transport' => 'invalid']],
        ];
    }

    public function testSchemaRequiresObjectSourceEvenWithAuto(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new YandexDriver())->buildRequest(self::CONFIG, '[]', [
            'folder_id' => 'folder', 'format' => 'json', 'response_schema' => self::SCHEMA,
        ]);
    }

    public function testDirectParserSelectsNativeForSchema(): void
    {
        self::assertSame('{"title":"Hola"}', (new YandexDriver())->parseResponse([], $this->response(), [
            'response_schema' => self::SCHEMA,
        ])['text']);
    }

    /** @dataProvider rejectedResponses */
    public function testSchemaParserPreservesFailureGuards(array $response): void
    {
        $this->expectException(\RuntimeException::class);
        (new YandexDriver())->parseResponse([], json_encode($response, JSON_THROW_ON_ERROR), [
            'response_schema' => self::SCHEMA,
        ]);
    }

    public static function rejectedResponses(): array
    {
        return [
            'truncated' => [['result' => ['alternatives' => [[
                'status' => 'ALTERNATIVE_STATUS_TRUNCATED_FINAL', 'message' => ['text' => '{}'],
            ]]]]],
            'refusal' => [['choices' => [['finish_reason' => 'stop', 'message' => [
                'refusal' => 'no', 'content' => '{}',
            ]]]]],
            'invalid JSON' => [['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '[]']]]]],
        ];
    }

    private function response(): string
    {
        return json_encode(['result' => ['alternatives' => [[
            'status' => 'ALTERNATIVE_STATUS_FINAL', 'message' => ['text' => '{"title":"Hola"}'],
        ]]]], JSON_THROW_ON_ERROR);
    }
}
