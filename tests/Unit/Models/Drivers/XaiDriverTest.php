<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\XaiDriver;
use PHPUnit\Framework\TestCase;

final class XaiDriverTest extends TestCase
{
    /** @test */
    public function reasoningAndOutputBudgetAreSentToChatCompletions(): void
    {
        $request = (new XaiDriver())->buildRequest([
            'endpoint' => 'https://api.x.ai/v1/chat/completions',
            'defaults' => ['model' => 'grok-4.20-0309-reasoning'],
            'requirements' => ['headers' => ['Content-Type: application/json']],
        ], 'Hello', [
            'reasoning_effort' => 'high',
            'max_completion_tokens' => 4096,
        ]);

        $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('grok-4.20-0309-reasoning', $payload['model']);
        self::assertSame('high', $payload['reasoning_effort']);
        self::assertSame(4096, $payload['max_tokens']);
        self::assertSame('https://api.x.ai/v1/chat/completions', $request['url']);
    }

    /** @test */
    public function nullTemperatureIsOmittedForReasoningModels(): void
    {
        $request = (new XaiDriver())->buildRequest([
            'endpoint' => 'https://api.x.ai/v1/chat/completions',
            'defaults' => ['model' => 'grok-4.7'],
            'requirements' => ['headers' => []],
        ], 'Hello', ['temperature' => null]);

        $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('temperature', $payload);
    }

    /** @test */
    public function responsesApiUsesReasoningAndParsesOutputMessages(): void
    {
        $driver = new XaiDriver();
        $request = $driver->buildRequest([
            'api_type' => 'responses',
            'endpoint' => 'https://api.x.ai/v1/responses',
            'defaults' => ['model' => 'grok-4.20-multi-agent-0309'],
            'requirements' => ['headers' => []],
        ], 'Hello', ['reasoning_effort' => 'low']);
        $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('low', $payload['reasoning']['effort']);
        self::assertArrayHasKey('input', $payload);
        self::assertArrayNotHasKey('messages', $payload);

        $result = $driver->parseResponse([], json_encode([
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => "‹‹TRANSLATION››\nПривет\n‹‹END››",
                ]],
            ]],
        ], JSON_UNESCAPED_UNICODE));
        self::assertSame('Привет', $result['text']);
    }

    /** @test */
    public function incompleteResponsesAreRejectedEvenWhenMarkersArePresent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('did not complete');
        (new XaiDriver())->parseResponse([], json_encode([
            'status' => 'incomplete',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => "‹‹TRANSLATION››ok‹‹END››",
                ]],
            ]],
        ], JSON_UNESCAPED_UNICODE));
    }

    /** @dataProvider invalidResponsesStatusProvider */
    public function testResponsesStatusMustBeCompletedWhenPresent(mixed $status): void
    {
        $this->expectException(\RuntimeException::class);
        (new XaiDriver())->parseResponse([], json_encode([
            'status' => $status,
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => "‹‹TRANSLATION››partial‹‹END››"]],
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<string,array{mixed}> */
    public static function invalidResponsesStatusProvider(): array
    {
        return ['null' => [null], 'integer' => [1], 'queued' => ['queued']];
    }

    /** @test */
    public function outputTextPartsAreJoinedBeforeMarkerParsing(): void
    {
        $result = (new XaiDriver())->parseResponse([], json_encode([
            'status' => 'completed',
            'output' => [
                ['type' => 'reasoning', 'summary' => []],
                ['type' => 'message', 'content' => [
                    ['type' => 'output_text', 'text' => '‹‹TRANS'],
                    ['type' => 'output_text', 'text' => "LATION››ok‹‹END››"],
                ]],
            ],
        ], JSON_UNESCAPED_UNICODE));
        self::assertSame('ok', $result['text']);
    }

    /** @test */
    public function responsesRefusalIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('refusal');
        (new XaiDriver())->parseResponse([], json_encode([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [
                ['type' => 'refusal', 'refusal' => 'Cannot translate this'],
            ]]],
        ], JSON_UNESCAPED_UNICODE));
    }

    /** @dataProvider rejectedChatFinishReasonProvider */
    public function testChatFinishReasonMustBeStopWhenPresent(mixed $finishReason): void
    {
        $this->expectException(\RuntimeException::class);
        (new XaiDriver())->parseResponse([], json_encode([
            'choices' => [[
                'finish_reason' => $finishReason,
                'message' => ['content' => "‹‹TRANSLATION››partial‹‹END››"],
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<string,array{mixed}> */
    public static function rejectedChatFinishReasonProvider(): array
    {
        return [
            'content filter' => ['content_filter'],
            'tool calls' => ['tool_calls'],
            'function call' => ['function_call'],
            'unknown' => ['other'],
            'null' => [null],
            'array' => [[]],
        ];
    }

    public function testLegacyChatMayOmitFinishReason(): void
    {
        $result = (new XaiDriver())->parseResponse([], json_encode([
            'choices' => [['message' => ['content' => "‹‹TRANSLATION››ok‹‹END››"]]],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->assertSame('ok', $result['text']);
    }
}
