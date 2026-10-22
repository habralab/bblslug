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
}
