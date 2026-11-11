<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models;

use Bblslug\Models\Drivers\OpenAiDriver;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\UsageExtractor;
use PHPUnit\Framework\TestCase;

final class DeepSeekProviderTest extends TestCase
{
    private const MODEL = 'deepseek:deepseek-v4-pro';

    /** @return array<string,mixed> */
    private function config(): array
    {
        $config = (new ModelRegistry())->get(self::MODEL);
        self::assertNotNull($config);
        return $config;
    }

    /** @return array<string,mixed> */
    private function schema(): array
    {
        return ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]];
    }

    public function testResponsesRequestUsesProviderDefaults(): void
    {
        $registry = new ModelRegistry();
        $driver = $registry->getDriver(self::MODEL);
        $this->assertInstanceOf(OpenAiDriver::class, $driver);
        $this->assertSame('DEEPSEEK_API_KEY', $registry->getAuthEnv(self::MODEL));
        $config = $this->config();
        $this->assertSame('header', $config['requirements']['auth']['type']);
        $this->assertSame('Authorization', $config['requirements']['auth']['key_name']);
        $this->assertSame('Bearer', $config['requirements']['auth']['prefix']);
        $request = $driver->buildRequest($config, 'Hello', ['format' => 'text']);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://api.deepseek.com/responses', $request['url']);
        $this->assertSame('deepseek-v4-pro', $body['model']);
        $this->assertSame(['effort' => 'none'], $body['reasoning']);
        $this->assertSame(4096, $body['max_output_tokens']);
        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertSame('system', $body['input'][0]['role']);
        $this->assertSame('user', $body['input'][1]['role']);
        $this->assertStringContainsString('Hello', $body['input'][1]['content']);

        $override = json_decode($driver->buildRequest($config, 'Hello', [
            'reasoning_effort' => 'high', 'max_completion_tokens' => 8192,
        ])['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('high', $override['reasoning']['effort']);
        $this->assertSame(8192, $override['max_output_tokens']);
    }

    public function testExplicitSchemaUsesResponsesFormatWithoutStrictClaim(): void
    {
        $config = $this->config();
        $driver = (new ModelRegistry())->getDriver(self::MODEL);
        $body = json_decode($driver->buildRequest($config, '{"message":"Hello"}', [
            'format' => 'structured_json', 'response_schema' => $this->schema(),
            'response_schema_name' => 'translation',
        ])['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'type' => 'json_schema', 'name' => 'translation', 'schema' => $this->schema(),
        ], $body['text']['format']);
        $this->assertSame('{"message":"Hello"}', $body['input'][1]['content']);
        $this->assertTrue($config['capabilities']['native_json_schema'] ?? false);
    }

    public function testStrictSchemaGuaranteeIsRejected(): void
    {
        $config = $this->config();
        $this->expectException(\InvalidArgumentException::class);
        (new ModelRegistry())->getDriver(self::MODEL)->buildRequest($config, '{}', [
            'format' => 'structured_json', 'response_schema' => $this->schema(), 'strict' => true,
        ]);
    }

    public function testOnlyFinalOutputTextIsReturnedAndUsageIsMapped(): void
    {
        $config = $this->config();
        $result = (new ModelRegistry())->getDriver(self::MODEL)->parseResponse($config, json_encode([
            'status' => 'completed',
            'output' => [
                ['type' => 'reasoning', 'content' => [
                    ['type' => 'reasoning_text', 'text' => '‹‹TRANSLATION››decoy‹‹END››'],
                ]],
                ['type' => 'message', 'content' => [
                    ['type' => 'output_text', 'text' => ' {"message":"Hola"} '],
                ]],
            ],
            'usage' => [
                'input_tokens' => 10, 'input_tokens_details' => ['cached_tokens' => 4],
                'output_tokens' => 8, 'output_tokens_details' => ['reasoning_tokens' => 3],
                'total_tokens' => 18,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), [
            'format' => 'structured_json', 'response_schema' => $this->schema(),
        ]);
        $this->assertSame('{"message":"Hola"}', $result['text']);
        $this->assertSame(['tokens' => ['total' => 18, 'breakdown' => [
            'prompt' => 10, 'completion' => 8, 'cached' => 4, 'reasoning' => 3,
        ]]], UsageExtractor::extract($config, $result['usage']));
    }

    /** @dataProvider rejectedResponses */
    public function testUnsuccessfulResponsesAreRejected(array $response): void
    {
        $config = $this->config();
        $this->expectException(\RuntimeException::class);
        (new ModelRegistry())->getDriver(self::MODEL)->parseResponse(
            $config,
            json_encode($response, JSON_THROW_ON_ERROR),
            ['format' => 'structured_json', 'response_schema' => $this->schema()]
        );
    }

    /** @return array<string,array{array<string,mixed>}> */
    public static function rejectedResponses(): array
    {
        return [
            'blank output' => [['status' => 'completed', 'output' => [[
                'type' => 'message', 'content' => [['type' => 'output_text', 'text' => " \n "]],
            ]]]],
            'failed' => [['status' => 'failed']],
            'incomplete' => [['status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens']]],
            'error' => [['error' => ['message' => 'provider error']]],
            'refusal' => [['status' => 'completed', 'output' => [[
                'type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'policy']],
            ]]]],
        ];
    }

    /** @dataProvider facadeTranslations */
    public function testFacadeTranslatesWithResponses(string $format, string $input, string $translated): void
    {
        $result = $this->runFacade($format, $input, $translated);
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame($translated, $result['result']);
        self::assertSame(1, $result['calls']);
        self::assertSame(18, $result['consumed']['tokens']['total']);
        self::assertSame(4, $result['consumed']['tokens']['breakdown']['cached']);
        if ($format === 'structured_json') {
            self::assertSame('json_schema', $result['request']['text']['format']['type']);
            self::assertArrayNotHasKey('strict', $result['request']['text']['format']);
        }
    }

    /** @return array<string,array{string,string,string}> */
    public static function facadeTranslations(): array
    {
        return [
            'text' => ['text', 'Hello world', 'Hola mundo'],
            'html' => ['html', '<p>Hello <strong>world</strong></p>', '<p>Hola <strong>mundo</strong></p>'],
            'nested json' => ['json', '{"nested":{"message":"Hello","count":7}}',
                '{"nested":{"message":"Hola","count":7}}'],
            'explicit schema' => ['structured_json', '{"nested":{"message":"Hello","count":7}}',
                '{"nested":{"message":"Hola","count":7}}'],
        ];
    }

    public function testFacadeStillRejectsChangedJsonScalarsWithExplicitSchema(): void
    {
        $result = $this->runFacade('structured_json', '{"count":7}', '{"count":8}');
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('JSON scalar values changed', $result['message']);
    }

    public function testHtmlSegmentsAutomaticallyUseNativeSchema(): void
    {
        $result = $this->runFacade('html', '<p>Hello</p>', '{"s0":"Hola"}', true);
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame('<p>Hola</p>', $result['result']);
        self::assertSame('json_schema', $result['request']['text']['format']['type']);
        self::assertArrayHasKey('s0', $result['request']['text']['format']['schema']['properties']);
        self::assertArrayNotHasKey('strict', $result['request']['text']['format']);
    }

    /** @return array<string,mixed> */
    private function runFacade(string $format, string $input, string $translated, bool $segments = false): array
    {
        $script = <<<'PHP'
namespace Bblslug {
    class HttpClient {
        public static string $body = '';
        public static string $response = '';
        public static int $calls = 0;
        public static function request(...$arguments): array {
            self::$calls++;
            self::$body = (string) ($arguments['body'] ?? '');
            return ['body' => self::$response, 'debugRequest' => '', 'debugResponse' => '',
                'headers' => [], 'status' => 200];
        }
    }
}
namespace {
    $scenario = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    require getcwd() . '/vendor/autoload.php';
    $text = $scenario['format'] === 'structured_json' || $scenario['segments'] ? $scenario['translated']
        : "‹‹TRANSLATION››\n" . $scenario['translated'] . "\n‹‹END››";
    \Bblslug\HttpClient::$response = json_encode(['status' => 'completed', 'output' => [
        ['type' => 'reasoning', 'content' => [['type' => 'reasoning_text',
            'text' => '‹‹TRANSLATION››decoy‹‹END››']]],
        ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]],
    ], 'usage' => ['input_tokens' => 10, 'input_tokens_details' => ['cached_tokens' => 4],
        'output_tokens' => 8, 'output_tokens_details' => ['reasoning_tokens' => 3], 'total_tokens' => 18]],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    try {
        $variables = $scenario['format'] === 'structured_json'
            ? ['response_schema' => ['type' => 'object', 'additionalProperties' => true]] : [];
        $result = \Bblslug\Bblslug::translate(apiKey: 'fixture-key', format: $scenario['format'],
            modelKey: 'deepseek:deepseek-v4-pro', text: $scenario['input'], variables: $variables,
            htmlTransport: $scenario['segments'] ? 'segments' : 'document');
        echo json_encode(['type' => 'success', 'result' => $result['result'], 'consumed' => $result['consumed'],
            'calls' => \Bblslug\HttpClient::$calls,
            'request' => json_decode(\Bblslug\HttpClient::$body, true, 512, JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR);
    } catch (\Throwable $e) {
        echo json_encode(['type' => $e::class, 'message' => $e->getMessage()], JSON_THROW_ON_ERROR);
    }
}
PHP;
        $process = proc_open([PHP_BINARY, '-r', $script], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes, dirname(__DIR__, 3));
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode(compact('format', 'input', 'translated', 'segments'), JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        return json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
