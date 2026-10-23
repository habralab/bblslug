<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use Bblslug\Bblslug;
use PHPUnit\Framework\TestCase;

final class BblslugStructuredOutputTest extends TestCase
{
    public function testNativeFormatsPassOptionsThroughTheFacadeAndValidateReturnedJson(): void
    {
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash'] as $model) {
            $result = $this->runFixture([
                'model' => $model,
                'input' => '{"message":"Hello"}',
                'translated' => '{"message":"Привет"}',
            ]);
            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame('{"message":"Привет"}', $result['result']);
            if (str_starts_with($model, 'openai:')) {
                self::assertSame('json_schema', $result['request']['response_format']['type']);
            } else {
                self::assertSame('application/json', $result['request']['generationConfig']['responseMimeType']);
            }
        }
    }

    public function testNativeFormatsRejectChangedNonStringScalarValues(): void
    {
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash'] as $model) {
            $result = $this->runFixture([
                'model' => $model,
                'input' => '{"nested":{"count":7,"ratio":0.12345678901234567,"enabled":true}}',
                'translated' => '{"nested":{"count":8,"ratio":0.12345678901234568,"enabled":false}}',
            ]);

            self::assertSame(\RuntimeException::class, $result['type']);
            self::assertStringContainsString('JSON scalar values changed', $result['message']);
        }
    }

    public function testNativeFormatsAllowChangedStringsWithScalarLookingText(): void
    {
        $input = json_encode([
            'number' => '7', 'boolean' => 'true', 'null' => 'null', 'text' => 'Source "ok" path',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $translated = json_encode([
            'number' => '8', 'boolean' => 'false', 'null' => 'null', 'text' => 'Перевод "ok" \\ путь',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash'] as $model) {
            $result = $this->runFixture([
                'model' => $model,
                'input' => $input,
                'translated' => $translated,
            ]);

            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame($translated, $result['result']);
        }
    }

    public function testNativeOutputCannotOmitNullsOrChangeNestedContainerTypes(): void
    {
        foreach (
            [
                ['{"message":"Hello","optional":null}', '{"message":"Привет"}'],
                ['{"items":{}}', '{"items":[]}'],
            ] as [$input, $translated]
        ) {
            $result = $this->runFixture(['input' => $input, 'translated' => $translated]);
            self::assertSame(\RuntimeException::class, $result['type']);
            self::assertStringContainsString('Schema mismatch', $result['message']);
        }
    }

    public function testBrokenNativeJsonIsRejected(): void
    {
        $result = $this->runFixture(['input' => '{"message":"Hello"}', 'translated' => '{"message":"bad "quote""}']);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('JSON syntax broken', $result['message']);
    }

    public function testUnsupportedNativeProviderFailsBeforeHttp(): void
    {
        $result = $this->runFixture(['model' => 'deepl:free', 'input' => '{}', 'translated' => '{}']);
        self::assertSame(\InvalidArgumentException::class, $result['type']);
        self::assertStringContainsString('only by OpenAI and Google', $result['message']);
    }

    public function testOldPositionalOptionalArgumentsKeepTheirMeaning(): void
    {
        $result = Bblslug::translate(
            'fixture-key',
            'json',
            'openai:gpt-5-mini',
            '{"message":"Hello"}',
            null,
            true,
            [],
            'translator',
            null,
            'EN',
            'DE',
            true,
            [],
            false
        );
        self::assertSame('{"message":"Hello"}', $result['result']);
        self::assertSame(0, $result['httpStatus']);
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/structured_output.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        return json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
