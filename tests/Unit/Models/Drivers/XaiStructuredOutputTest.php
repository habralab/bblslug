<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\XaiDriver;
use PHPUnit\Framework\TestCase;

final class XaiStructuredOutputTest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object', 'properties' => ['s0' => ['type' => 'string'], 's1' => ['type' => 'string']],
        'required' => ['s0', 's1'], 'additionalProperties' => false,
    ];

    public function testBothEndpointsReceiveSchemaAndUnwrappedSource(): void
    {
        foreach (['responses', 'chat_completions'] as $api) {
            $request = (new XaiDriver())->buildRequest([
                'api_type' => $api, 'defaults' => ['model' => 'grok-4.7'],
            ], '{"s0":"Section 1","s1":"Section 1"}', [
                'format' => 'structured_json', 'response_schema' => self::SCHEMA,
                'response_schema_name' => 'translation',
            ]);
            $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
            $format = $api === 'responses' ? $body['text']['format'] : $body['response_format'];
            self::assertSame('json_schema', $format['type']);
            $spec = $api === 'responses' ? $format : $format['json_schema'];
            self::assertSame(self::SCHEMA, $spec['schema']);
            self::assertTrue($spec['strict']);
            self::assertSame('translation', $spec['name']);
            $messages = $body[$api === 'responses' ? 'input' : 'messages'];
            self::assertSame('{"s0":"Section 1","s1":"Section 1"}', $messages[1]['content']);
            self::assertStringNotContainsString('‹‹TRANSLATION››', $messages[0]['content']);
        }
    }

    public function testSchemaResponsePreservesJsonEscapingAndUsage(): void
    {
        $json = json_encode(['s0' => 'Abschnitt "1"', 's1' => 'C:\\tmp'], JSON_THROW_ON_ERROR);
        foreach (['responses', 'chat_completions'] as $api) {
            $response = $api === 'responses'
                ? ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [
                    ['type' => 'output_text', 'text' => $json],
                ]]]]
                : ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => $json]]]];
            $response['usage'] = ['total_tokens' => 10];
            $result = (new XaiDriver())->parseResponse([], json_encode($response, JSON_THROW_ON_ERROR), [
                'format' => 'json', 'response_schema' => self::SCHEMA,
            ]);
            self::assertSame($json, $result['text']);
            self::assertSame(['total_tokens' => 10], $result['usage']);
        }
    }

    /** @dataProvider invalidOptions */
    public function testInvalidSchemaSettingsFailBeforeBuildingRequest(array $options): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new XaiDriver())->buildRequest(['defaults' => ['model' => 'grok']], '{}', $options);
    }

    public static function invalidOptions(): array
    {
        return [
            [['format' => 'structured_json']],
            [['format' => 'json', 'response_schema' => []]],
            [['format' => 'json', 'response_schema' => ['object']]],
            [['format' => 'json', 'response_schema' => null]],
            [['format' => 'html', 'response_schema' => self::SCHEMA]],
            [['format' => 'json', 'response_schema' => self::SCHEMA, 'json_transport' => 'markers']],
            [['format' => 'json', 'response_schema' => self::SCHEMA, 'strict' => 'false']],
            [['format' => 'json', 'response_schema' => self::SCHEMA, 'response_schema_name' => 'bad name']],
        ];
    }

    /** @dataProvider invalidNativeResponses */
    public function testSchemaDoesNotBypassCompletionOrRefusalChecks(array $response): void
    {
        $this->expectException(\RuntimeException::class);
        (new XaiDriver())->parseResponse([], json_encode($response, JSON_THROW_ON_ERROR), [
            'response_schema' => self::SCHEMA,
        ]);
    }

    public static function invalidNativeResponses(): array
    {
        return [
            [['status' => 'incomplete', 'output' => [['type' => 'message', 'content' => [
                ['type' => 'output_text', 'text' => '{"s0":"A","s1":"B"}'],
            ]]]]],
            [['status' => 'completed', 'output' => [['type' => 'message', 'content' => [
                ['type' => 'refusal', 'refusal' => 'Declined'],
            ]]]]],
            [['choices' => [['finish_reason' => 'stop', 'message' => [
                'content' => '{}', 'refusal' => 'Declined',
            ]]]]],
            [['choices' => [['finish_reason' => 'length', 'message' => ['content' => '{}']]]]],
            [['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '```json {} ```']]]]],
        ];
    }
}
