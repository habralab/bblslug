<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugJsonOutputTest extends TestCase
{
    public function testLinedJsonRejectsMalformedAndStructurallyIncompleteOutput(): void
    {
        foreach (
            [
                ['{"message":"Hello"}', '{"message":"Привет""}', 'JSON syntax broken'],
                ['{"message":"Hello","optional":null}', '{"message":"Привет"}', 'Schema mismatch'],
            ] as [$input, $translated, $message]
        ) {
            $result = $this->runFixture([
                'format' => 'linedJson', 'input' => $input, 'translated' => $translated,
            ]);
            $this->assertSame(\RuntimeException::class, $result['type']);
            $this->assertStringContainsString($message, $result['message']);
        }
    }

    /** @test */
    public function jsonFormatsRejectChangedNonStringScalarValues(): void
    {
        foreach (['json', 'linedJson'] as $format) {
            foreach (
                [
                    [
                        '{"nested":{"count":7,"ratio":0.12345678901234567,"enabled":true}}',
                        '{"nested":{"count":8,"ratio":0.12345678901234567,"enabled":true}}',
                    ],
                    [
                        '{"nested":{"count":7,"ratio":0.12345678901234567,"enabled":true}}',
                        '{"nested":{"count":7,"ratio":0.12345678901234568,"enabled":true}}',
                    ],
                    [
                        '{"nested":{"count":7,"ratio":0.12345678901234567,"enabled":true}}',
                        '{"nested":{"count":7,"ratio":0.12345678901234567,"enabled":false}}',
                    ],
                    ['{"large":9223372036854775808}', '{"large":9223372036854775809}'],
                    ['{"large":9007199254740992}', '{"large":9007199254740993}'],
                    ['{"number":1.0}', '{"number":1e0}'],
                    ['{"values":[true,false]}', '{"values":[false,true]}'],
                    ['{"ratio":0.12345678901234567891}', '{"ratio":0.12345678901234567892}'],
                ] as [$input, $translated]
            ) {
                $result = $this->runFixture([
                    'format' => $format,
                    'input' => $input,
                    'translated' => $translated,
                ]);

                $this->assertSame(\RuntimeException::class, $result['type']);
                $this->assertStringContainsString('JSON scalar values changed', $result['message']);
            }
        }
    }

    /** @test */
    public function jsonFormatsAllowChangedStringsWithScalarLookingText(): void
    {
        $input = json_encode([
            'nested' => [
                'number' => '7', 'boolean' => 'true', 'null' => 'null', 'text' => 'Source "ok" path',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $translated = json_encode([
            'nested' => [
                'number' => '8', 'boolean' => 'false', 'null' => 'null', 'text' => 'Перевод "ok" \\ путь',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runFixture([
                'format' => $format,
                'input' => $input,
                'translated' => $translated,
            ]);

            $this->assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            $this->assertSame($translated, $result['result']);
        }
    }

    /** @test */
    public function jsonFormatsRejectNullChangedToAnotherType(): void
    {
        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runFixture([
                'format' => $format,
                'input' => '{"nested":{"value":null}}',
                'translated' => '{"nested":{"value":false}}',
            ]);

            $this->assertSchemaMismatch($result);
        }
    }

    /** @test */
    public function validationDisabledKeepsChangedJsonScalarsPermissive(): void
    {
        $translated = '{"nested":{"count":8,"enabled":false}}';
        $result = $this->runFixture([
            'input' => '{"nested":{"count":7,"enabled":true}}',
            'translated' => $translated,
            'validate' => false,
        ]);

        $this->assertSame('success', $result['type']);
        $this->assertSame($translated, $result['result']);
    }

    /** @test */
    public function emptyObjectAndListCannotBeSwappedAtRoot(): void
    {
        foreach ([['{}', '[]'], ['[]', '{}']] as [$input, $translated]) {
            $result = $this->runFixture([
                'input' => $input,
                'translated' => $translated,
            ]);

            $this->assertSchemaMismatch($result);
        }
    }

    /** @test */
    public function emptyObjectAndListCannotBeSwappedWhenNested(): void
    {
        foreach (
            [
                ['{"value":{}}', '{"value":[]}'],
                ['{"value":[]}', '{"value":{}}'],
            ] as [$input, $translated]
        ) {
            $result = $this->runFixture([
                'input' => $input,
                'translated' => $translated,
            ]);

            $this->assertSchemaMismatch($result);
        }
    }

    /** @test */
    public function numericKeyObjectCannotBecomeList(): void
    {
        $result = $this->runFixture([
            'input' => '{"0":"source"}',
            'translated' => '["translation"]',
        ]);

        $this->assertSchemaMismatch($result);
    }

    /** @test */
    public function nestedNumericKeyObjectCannotBecomeList(): void
    {
        $result = $this->runFixture([
            'input' => '{"value":{"0":"source"}}',
            'translated' => '{"value":["translation"]}',
        ]);

        $this->assertSchemaMismatch($result);
    }

    /** @test */
    public function nestedMemberTypeChangesAreRejected(): void
    {
        $result = $this->runFixture([
            'input' => '{"value":{"text":"source"}}',
            'translated' => '{"value":{"text":1}}',
        ]);

        $this->assertSchemaMismatch($result);
    }

    /** @test */
    public function nestedObjectsAndListsWithTranslatedStringsArePreserved(): void
    {
        $translated = '{"article":{"title":"Перевод","items":[{"text":"Первый"},'
            . '{"text":"Второй"}]}}';
        $result = $this->runFixture([
            'input' => '{"article":{"title":"Source","items":[{"text":"First"},'
                . '{"text":"Second"}]}}',
            'translated' => $translated,
        ]);

        $this->assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        $this->assertSame($translated, $result['result']);
    }

    /** @test */
    public function missingTrailingObjectNullIsRejected(): void
    {
        $result = $this->runFixture([
            'input' => '{"title":"source","optional":null}',
            'translated' => '{"title":"перевод"}',
        ]);

        $this->assertSchemaMismatch($result);
    }

    /** @test */
    public function missingNestedTrailingNullIsRejected(): void
    {
        $result = $this->runFixture([
            'input' => '{"item":{"text":"source","note":null}}',
            'translated' => '{"item":{"text":"перевод"}}',
        ]);

        $this->assertSchemaMismatch($result);
    }

    /** @test */
    public function missingTrailingListNullIsRejected(): void
    {
        $result = $this->runFixture([
            'input' => '{"items":["source",null]}',
            'translated' => '{"items":["перевод"]}',
        ]);

        $this->assertSchemaMismatch($result);
    }

    /** @test */
    public function missingObjectNullBeforeAnotherKeyIsRejected(): void
    {
        $result = $this->runFixture([
            'input' => '{"optional":null,"title":"source"}',
            'translated' => '{"title":"перевод"}',
        ]);

        $this->assertSchemaMismatch($result);
    }

    /** @test */
    public function intactJsonIsReturnedByteForByte(): void
    {
        $translated = '{"emptyObject":{},"emptyList":[],"number":1.0,"big":9223372036854775808,'
            . '"text":"перевод \"ok\" \\\\ путь"}';
        $result = $this->runFixture([
            'input' => '{"emptyObject":{},"emptyList":[],"number":1.0,"big":9223372036854775808,'
                . '"text":"source \"ok\" \\\\ path"}',
            'translated' => $translated,
        ]);

        $this->assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        $this->assertSame($translated, $result['result']);
        $this->assertSame(mb_strlen($translated), $result['lengths']['translated']);
        $raw = json_decode($result['rawResponseBody'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($translated, $raw['translations'][0]['text']);
    }

    /** @test */
    public function validationDisabledReturnsMissingNullUnmodified(): void
    {
        $translated = '{"title":"перевод"}';
        $result = $this->runFixture([
            'input' => '{"title":"source","optional":null}',
            'translated' => $translated,
            'validate' => false,
        ]);

        $this->assertSame('success', $result['type']);
        $this->assertSame($translated, $result['result']);
    }

    /**
     * @param array{input:string,translated:string,validate?:bool} $scenario
     * @return array<string,mixed>
     */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/json_output.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            []
        );
        $this->assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $stderr);

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($result, $stdout);
        return $result;
    }

    /** @param array<string,mixed> $result */
    private function assertSchemaMismatch(array $result): void
    {
        $this->assertSame('RuntimeException', $result['type']);
        $this->assertStringContainsString('Schema mismatch', $result['message']);
    }
}
