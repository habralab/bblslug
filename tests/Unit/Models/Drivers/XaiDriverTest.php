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
}
