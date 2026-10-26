<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Bblslug;
use Bblslug\Models\Drivers\OpenAiDriver;
use Bblslug\Models\ModelRegistry;
use PHPUnit\Framework\TestCase;

final class OpenAiDriverTest extends TestCase
{
    /** @return array<string,array{string,string,string}> */
    public function currentModelsAndFormats(): array
    {
        $cases = [];
        $formats = [
            'text' => "Привет, мир!\nSecond line.",
            'html' => '<p title="Greeting">Привет <code>echo "hi";</code></p>',
            'json' => json_encode([
                'article' => ['title' => 'Привет', 'html' => '<p title="Hello">Текст</p>'],
                'embedded' => json_encode(['message' => 'Привет', 'path' => 'C:\\docs\\"quoted"']),
                'id' => 123,
                'enabled' => true,
                'optional' => null,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
        foreach (['gpt-6-luna', 'gpt-6.1-sol', 'gpt-6-astra'] as $model) {
            foreach ($formats as $format => $input) {
                $cases[$model . '/' . $format] = ['openai:' . $model, $format, $input];
            }
        }
        return $cases;
    }

    /** @dataProvider currentModelsAndFormats */
    public function testCurrentModelsBuildReasoningRequestsAndDryRunAllFormats(
        string $modelKey,
        string $format,
        string $input
    ): void {
        $config = (new ModelRegistry())->get($modelKey);
        $this->assertNotNull($config);
        $request = (new OpenAiDriver())->buildRequest($config, $input, ['format' => $format]);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(substr($modelKey, 7), $body['model']);
        $this->assertSame('low', $body['reasoning_effort']);
        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertSame("‹‹TRANSLATION››\n{$input}\n‹‹END››", $body['messages'][1]['content']);

        $result = Bblslug::translate(
            apiKey: 'DUMMY_NOT_A_REAL_KEY',
            format: $format,
            modelKey: $modelKey,
            text: $input,
            dryRun: true
        );
        $this->assertSame($input, $result['result']);
        $this->assertSame(0, $result['httpStatus']);
    }

    public function testLegacyTemperatureAndOutputBudgetOverrides(): void
    {
        $config = (new ModelRegistry())->get('openai:gpt-4o');
        $this->assertNotNull($config);
        $driver = new OpenAiDriver();
        $request = $driver->buildRequest($config, 'Hello', []);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(0.0, $body['temperature']);
        $this->assertArrayNotHasKey('reasoning_effort', $body);

        $request = $driver->buildRequest($config, 'Hello', [
            'temperature' => null,
            'reasoning_effort' => 'low',
            'max_completion_tokens' => '2048',
        ]);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertSame('low', $body['reasoning_effort']);
        $this->assertSame(2048, $body['max_completion_tokens']);
    }

    public function testInvalidOutputBudgetIsRejectedBeforeSending(): void
    {
        $config = (new ModelRegistry())->get('openai:gpt-6-luna');
        $this->assertNotNull($config);
        $this->expectException(\InvalidArgumentException::class);
        (new OpenAiDriver())->buildRequest($config, 'Hello', ['max_completion_tokens' => '1.5']);
    }

    public function testResponsesApiUsesNativeInputAndOutputOptions(): void
    {
        $config = [
            'endpoint' => 'https://api.openai.com/v1/responses',
            'api_type' => 'responses',
            'defaults' => ['model' => 'gpt-5.1', 'reasoning_effort' => 'low'],
        ];
        $driver = new OpenAiDriver();
        $body = json_decode($driver->buildRequest($config, 'Hello', [
            'max_completion_tokens' => 512,
        ])['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('gpt-5.1', $body['model']);
        $this->assertSame('low', $body['reasoning']['effort']);
        $this->assertSame(512, $body['max_output_tokens']);
        $this->assertSame('Hola', $driver->parseResponse($config, json_encode([
            'output' => [[
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => "‹‹TRANSLATION››\nHo"],
                    ['type' => 'output_text', 'text' => "la\n‹‹END››"],
                ],
            ]]], JSON_THROW_ON_ERROR))['text']);
    }

    public function testLegacyCompletionsApiUsesPromptAndParsesText(): void
    {
        $config = [
            'endpoint' => 'https://api.openai.com/v1/completions',
            'api_type' => 'completions',
            'defaults' => ['model' => 'text-davinci-003'],
        ];
        $driver = new OpenAiDriver();
        $body = json_decode($driver->buildRequest($config, 'Hello', [
            'max_completion_tokens' => 128,
        ])['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('text-davinci-003', $body['model']);
        $this->assertStringContainsString('Hello', $body['prompt']);
        $this->assertSame(128, $body['max_tokens']);
        $this->assertSame('Hola', $driver->parseResponse($config, json_encode([
            'choices' => [['text' => "‹‹TRANSLATION››\nHola\n‹‹END››"]],
        ], JSON_THROW_ON_ERROR))['text']);
    }

    public function testUnknownApiTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new OpenAiDriver())->buildRequest([
            'endpoint' => 'https://example.test',
            'api_type' => 'audio',
            'defaults' => ['model' => 'gpt-audio'],
        ], 'Hello', []);
    }

    public function testIncompleteResponsesAreRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new OpenAiDriver())->parseResponse([], json_encode([
            'status' => 'incomplete',
            'incomplete_details' => ['reason' => 'max_output_tokens'],
        ], JSON_THROW_ON_ERROR));
    }

    public function testResponsesRefusalIsReported(): void
    {
        $this->expectExceptionMessage('OpenAI response refused: policy restriction');
        (new OpenAiDriver())->parseResponse([], json_encode([
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'refusal', 'refusal' => 'policy restriction']],
            ]],
        ], JSON_THROW_ON_ERROR));
    }

    /** @dataProvider rejectedChatFinishReasonProvider */
    public function testChatFinishReasonMustBeStopWhenPresent(mixed $finishReason): void
    {
        $this->expectException(\RuntimeException::class);
        (new OpenAiDriver())->parseResponse([], json_encode([
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
        $result = (new OpenAiDriver())->parseResponse([], json_encode([
            'choices' => [['message' => ['content' => "‹‹TRANSLATION››ok‹‹END››"]]],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->assertSame('ok', $result['text']);
    }

    /** @dataProvider unexpectedResponsesStatusProvider */
    public function testResponsesStatusMustBeCompletedWhenPresent(mixed $status): void
    {
        $this->expectException(\RuntimeException::class);
        (new OpenAiDriver())->parseResponse([], json_encode([
            'status' => $status,
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => "‹‹TRANSLATION››partial‹‹END››"]],
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<string,array{mixed}> */
    public static function unexpectedResponsesStatusProvider(): array
    {
        return ['queued' => ['queued'], 'null' => [null], 'integer' => [1]];
    }
}
