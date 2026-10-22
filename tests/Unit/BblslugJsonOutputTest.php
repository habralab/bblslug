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
