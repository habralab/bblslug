<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use Bblslug\Bblslug;
use PHPUnit\Framework\TestCase;

final class BblslugStructuredOutputTest extends TestCase
{
    public function testRegisteredGeminiDefaultsToNativeJsonWithoutTransportOverride(): void
    {
        $result = $this->runFixture([
            'model' => 'google:gemini-2.5-flash',
            'format' => 'json',
            'variables' => [],
            'input' => '{"message":"Hello","count":7}',
            'translated' => '{"message":"Hallo","count":7}',
        ]);
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame('{"message":"Hallo","count":7}', $result['result']);
        self::assertSame('application/json', $result['request']['generationConfig']['responseMimeType']);
        self::assertArrayNotHasKey('responseJsonSchema', $result['request']['generationConfig']);
    }

    public function testGoogleNativeJsonWithoutSchemaUsesFacadeValidation(): void
    {
        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runFixture([
                'model' => 'google:gemini-2.5-flash',
                'format' => $format,
                'variables' => ['json_transport' => 'native'],
                'input' => '{"message":"Hello","count":7}',
                'translated' => '{"message":"Hallo","count":7}',
            ]);
            self::assertSame('success', $result['type'], $result['message'] ?? '');
            self::assertSame('{"message":"Hallo","count":7}', $result['result']);
            self::assertSame('application/json', $result['request']['generationConfig']['responseMimeType']);
            self::assertArrayNotHasKey('responseJsonSchema', $result['request']['generationConfig']);
        }
    }

    /** @dataProvider googleNativeInvalidOutputProvider */
    public function testGoogleNativeJsonDoesNotBypassValidation(string $output, string $reason): void
    {
        $result = $this->runFixture([
            'model' => 'google:gemini-2.5-flash',
            'format' => 'json',
            'variables' => ['json_transport' => 'native'],
            'input' => '{"message":"Hello","count":7}',
            'translated' => $output,
        ]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString($reason, $result['message']);
    }

    /** @return array<string,array{string,string}> */
    public static function googleNativeInvalidOutputProvider(): array
    {
        return [
            'broken syntax' => ['{"message":"Hallo","count":}', 'JSON syntax broken'],
            'changed structure' => ['{"message":"Hallo"}', 'Schema mismatch'],
            'changed scalar' => ['{"message":"Hallo","count":8}', 'JSON scalar values changed'],
            'added fences' => ["```json\n{\"message\":\"Hallo\",\"count\":7}\n```", 'JSON syntax broken'],
        ];
    }

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

    public function testNativeFormatsAllowObjectKeysToBeReorderedButKeepListOrder(): void
    {
        $input = '{"meta":{"0":"zero","1":"one","10":"ten","01":"leading"},'
            . '"items":[{"title":"First","details":{"a":"A","b":"B"}},'
            . '{"title":"Second","details":{"a":"C","b":"D"}}],"title":"Source"}';
        $translated = '{"title":"Перевод","items":[{"details":{"b":"Б","a":"А"},'
            . '"title":"Первый"},{"details":{"b":"Д","a":"Ц"},"title":"Второй"}],'
            . '"meta":{"01":"leading","10":"ten","1":"one","0":"zero"}}';
        $schema = [
            'type' => 'object',
            'additionalProperties' => true,
        ];

        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash'] as $model) {
            $result = $this->runFixture([
                'model' => $model,
                'input' => $input,
                'translated' => $translated,
                'schema' => $schema,
            ]);

            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame($translated, $result['result']);
        }
    }

    public function testNativeFormatsRejectScalarValuesMovedBetweenObjectKeys(): void
    {
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash'] as $model) {
            $result = $this->runFixture([
                'model' => $model,
                'input' => '{"a":1,"b":2,"nested":{"left":true,"right":false}}',
                'translated' => '{"b":1,"a":2,"nested":{"right":true,"left":false}}',
            ]);

            self::assertSame(\RuntimeException::class, $result['type']);
            self::assertStringContainsString('JSON scalar values changed', $result['message']);
        }
    }

    public function testNativeFormatsPreserveExactNonStringScalarLexemesWhenKeysMove(): void
    {
        $input = '{"decimal":0.12345678901234567891,"large":9223372036854775808,'
            . '"enabled":true,"empty":null,"nested":{"number":1.0}}';
        $translated = '{"nested":{"number":1.0},"empty":null,"enabled":true,'
            . '"large":9223372036854775808,"decimal":0.12345678901234567891}';
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
