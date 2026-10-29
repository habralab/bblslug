<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\AnthropicDriver;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\UsageExtractor;
use PHPUnit\Framework\TestCase;

final class AnthropicDriverTest extends TestCase
{
    /** @return array<string,array{string}> */
    public function currentModels(): array
    {
        return [
            'haiku' => ['anthropic:claude-haiku-5.5'],
            'sonnet' => ['anthropic:claude-sonnet-5.5'],
            'opus' => ['anthropic:claude-opus-5.5'],
        ];
    }

    /** @dataProvider currentModels */
    public function testBuildsMessagesRequestWithSeparateSystemPrompt(string $modelKey): void
    {
        $config = (new ModelRegistry())->get($modelKey);
        $this->assertNotNull($config);
        $text = '{"article":{"text":"Hello","embedded":"{\\"key\\":\\"value\\"}"}}';
        $request = (new AnthropicDriver())->buildRequest($config, $text, [
            'format' => 'json', 'maxTokens' => '2048', 'effort' => 'medium',
        ]);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://api.anthropic.com/v1/messages', $request['url']);
        $this->assertSame($config['defaults']['model'], $body['model']);
        $this->assertNotSame('', $body['system']);
        $this->assertCount(1, $body['messages']);
        $this->assertSame('user', $body['messages'][0]['role']);
        $this->assertSame("‹‹TRANSLATION››\n{$text}\n‹‹END››", $body['messages'][0]['content']);
        $this->assertSame(2048, $body['max_tokens']);
        $this->assertSame(['effort' => 'medium'], $body['output_config']);
        $this->assertArrayNotHasKey('temperature', $body);
    }

    public function testCombinesTextBlocksWithoutThinkingAndNormalizesUsage(): void
    {
        $response = json_encode([
            'content' => [
                ['type' => 'thinking', 'thinking' => '‹‹TRANSLATION››Do not return this‹‹END››'],
                ['type' => 'text', 'text' => "‹‹TRANSLATION››\nHallo "],
                ['type' => 'text', 'text' => "Welt\n‹‹END››"],
            ],
            'stop_reason' => 'end_turn',
            'usage' => [
                'input_tokens' => 5, 'output_tokens' => 3,
                'cache_creation_input_tokens' => 2, 'cache_read_input_tokens' => 7,
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $driver = new AnthropicDriver();
        $result = $driver->parseResponse([], $response);
        $this->assertSame('Hallo Welt', $result['text']);
        $config = (new ModelRegistry())->get('anthropic:claude-haiku-5.5');
        $this->assertNotNull($config);
        $consumed = UsageExtractor::extract($config, $result['usage']);
        $this->assertSame(17, $consumed['tokens']['total']);
        $this->assertSame(5, $consumed['tokens']['breakdown']['prompt']);
        $this->assertSame(3, $consumed['tokens']['breakdown']['completion']);
        $this->assertSame(2, $consumed['tokens']['breakdown']['cache_creation']);
        $this->assertSame(7, $consumed['tokens']['breakdown']['cache_read']);
    }

    /** @return array<string,array{string}> */
    public static function malformedBoundaries(): array
    {
        return [
            'shortened end' => ["‹‹TRANSLATION››\n{\"message\":\"Hallo\"}\n\n‹‹END›"],
            'altered start' => ['‹‹TRANSLATION›Hallo‹‹END››'],
            'reversed markers' => ['‹‹END››Hallo‹‹TRANSLATION››'],
            'absent markers' => ['Hallo'],
        ];
    }

    /** @dataProvider malformedBoundaries */
    public function testRejectsMalformedBoundariesOnCompletedResponse(string $text): void
    {
        $response = json_encode([
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Markers not found in Anthropic response');
        (new AnthropicDriver())->parseResponse([], $response);
    }

    /** @return array<string,array{string}> */
    public function incompleteResponses(): array
    {
        return [
            'truncated' => ['max_tokens'],
            'context exceeded' => ['model_context_window_exceeded'],
            'refused' => ['refusal'],
            'tool use' => ['tool_use'],
            'paused' => ['pause_turn'],
        ];
    }

    /** @dataProvider incompleteResponses */
    public function testRejectsIncompleteResponsesEvenIfTheyContainMarkers(string $stopReason): void
    {
        $response = json_encode([
            'content' => [['type' => 'text', 'text' => '‹‹TRANSLATION››Partial‹‹END››']],
            'stop_reason' => $stopReason,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($stopReason);
        (new AnthropicDriver())->parseResponse([], $response);
    }

    public function testProviderErrorPreservesItsExplanation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Anthropic API error: max_tokens must be greater than 0');
        (new AnthropicDriver())->parseResponse([], json_encode([
            'type' => 'error',
            'error' => ['type' => 'invalid_request_error', 'message' => 'max_tokens must be greater than 0'],
        ], JSON_THROW_ON_ERROR));
    }

    public function testInsufficientCreditProviderErrorPreservesItsExplanation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Anthropic API error: Your credit balance is too low to access the Anthropic API. '
            . 'Please go to Plans & Billing to upgrade or purchase credits.'
        );
        (new AnthropicDriver())->parseResponse([], json_encode([
            'type' => 'error',
            'error' => [
                'type' => 'invalid_request_error',
                'message' => 'Your credit balance is too low to access the Anthropic API. '
                    . 'Please go to Plans & Billing to upgrade or purchase credits.',
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function testRejectsFractionalOutputBudgetBeforeSending(): void
    {
        $config = (new ModelRegistry())->get('anthropic:claude-haiku-5.5');
        $this->assertNotNull($config);
        $this->expectException(\InvalidArgumentException::class);
        (new AnthropicDriver())->buildRequest($config, 'Hello', ['maxTokens' => '1.5']);
    }

    public function testAdaptiveThinkingOmitsTemperatureAndUsesEffort(): void
    {
        $request = (new AnthropicDriver())->buildRequest([
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'defaults' => [
                'model' => 'claude-opus-4-8',
                'temperature' => 0.2,
                'thinking' => 'adaptive',
            ],
        ], 'Hello', ['effort' => 'high']);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertSame(['type' => 'adaptive'], $body['thinking']);
        $this->assertSame(['effort' => 'high'], $body['output_config']);
    }

    public function testLegacyThinkingKeepsTemperatureAndDoesNotInventEffort(): void
    {
        $request = (new AnthropicDriver())->buildRequest([
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'defaults' => ['model' => 'claude-3-haiku-20240307', 'temperature' => 0.2],
        ], 'Hello', []);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0.2, $body['temperature']);
        $this->assertArrayNotHasKey('thinking', $body);
        $this->assertArrayNotHasKey('output_config', $body);
    }
}
