<?php

declare(strict_types=1);

namespace BblslugTests\Models\Drivers;

use Bblslug\Models\Drivers\GoogleDriver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GoogleStructuredOutputTest extends TestCase
{
    /** @return array<string,mixed> */
    private function schema(): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [
                'items' => [
                    'type'  => 'array',
                    'items' => ['type' => 'object', 'properties' => ['text' => ['type' => 'string']]],
                ],
            ],
            'required'             => ['items'],
            'additionalProperties' => false,
        ];
    }

    public function testNativeModePreservesSchemaAndUsesRawInput(): void
    {
        $schema = $this->schema();
        $request = (new GoogleDriver())->buildRequest(
            ['endpoint' => 'https://example.test/generate'],
            '{"items":[{"text":"Hello"}]}',
            ['format' => 'json', 'response_schema' => $schema, 'promptKey' => 'translator']
        );
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($schema, $body['generationConfig']['responseJsonSchema']);
        $this->assertSame('application/json', $body['generationConfig']['responseMimeType']);
        $this->assertSame('{"items":[{"text":"Hello"}]}', $body['contents'][0]['parts'][0]['text']);
        $this->assertStringContainsString(
            'professional translator of JSON content',
            $body['system_instruction']['parts'][0]['text']
        );
        $this->assertStringNotContainsString('‹‹TRANSLATION››', $body['contents'][0]['parts'][0]['text']);
    }

    public function testStructuredJsonWithoutSchemaThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires');
        (new GoogleDriver())->buildRequest([], 'input', ['format' => 'structured_json']);
    }

    /** @dataProvider invalidSchemaProvider */
    public function testInvalidSchemaThrows(mixed $schema): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GoogleDriver())->buildRequest([], 'input', ['response_schema' => $schema]);
    }

    /** @return array<string,array{mixed}> */
    public static function invalidSchemaProvider(): array
    {
        return [
            'missing' => [null],
            'empty'   => [[]],
            'list'    => [['type', 'object']],
        ];
    }

    public function testNativeResponseConcatenatesOnlyNonThoughtTextParts(): void
    {
        $response = json_encode([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content'      => ['parts' => [
                    ['text' => '{"items":'],
                    ['text' => 'internal reasoning', 'thought' => true],
                    ['text' => '[{"text":"Hola"}]}'],
                ]],
            ]],
            'usageMetadata' => ['totalTokenCount' => 12],
        ], JSON_THROW_ON_ERROR);

        $result = (new GoogleDriver())->parseResponse([], $response, ['response_schema' => $this->schema()]);

        $this->assertSame('{"items":[{"text":"Hola"}]}', $result['text']);
        $this->assertSame(['totalTokenCount' => 12], $result['usage']);
    }

    /** @dataProvider rejectedFinishReasonProvider */
    public function testNativeResponseRejectsBlockedAndTruncatedResponses(string $reason): void
    {
        $response = json_encode([
            'candidates' => [['finishReason' => $reason, 'content' => ['parts' => [['text' => '{}']]]]],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(RuntimeException::class);
        (new GoogleDriver())->parseResponse([], $response, ['response_schema' => $this->schema()]);
    }

    /** @return array<string,array{string}> */
    public static function rejectedFinishReasonProvider(): array
    {
        return ['blocked' => ['SAFETY'], 'truncated' => ['MAX_TOKENS']];
    }

    public function testNonNativeResponseStillUsesMarkersAndIgnoresThoughtParts(): void
    {
        $response = json_encode([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content'      => ['parts' => [
                    ['text' => '‹‹TRANSLATION››Hello '],
                    ['text' => 'reasoning', 'thought' => true],
                    ['text' => 'world‹‹END››'],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->assertSame('Hello world', (new GoogleDriver())->parseResponse([], $response)['text']);
    }
}
