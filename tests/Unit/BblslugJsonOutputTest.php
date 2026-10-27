<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugJsonOutputTest extends TestCase
{
    public function testFilteredJsonSentToProviderKeepsEscapesAndNestedContainersValid(): void
    {
        $inner = <<<'JSON'
{"body":"\u0020https://example.org/path \u003Ccode\u003Efixed\u003C/code\u003E","literal":"\u0031@@0@@"}
JSON;
        foreach ([false, true] as $nested) {
            $source = $nested ? json_encode(['embedded' => $inner], JSON_THROW_ON_ERROR) : $inner;
            foreach ([['url', 'html_code'], ['html_code', 'url']] as $filters) {
                $result = $this->runFixture([
                    'input' => $source, 'filters' => $filters, 'echoPrepared' => true,
                    'checkEmbedded' => $nested, 'validateEmbeddedJson' => $nested,
                ]);
                self::assertSame('success', $result['type'], $result['message'] ?? '');
                self::assertSame($source, $result['result']);
                self::assertSame(1, $result['httpCalls']);
            }
        }
    }

    public function testEncodedMarkerKeyRemainsCompatibleWithNativeSchema(): void
    {
        $source = <<<'JSON'
{"\u0040\u00405\u0040\u0040":"fixed","url":"https://example.org/path"}
JSON;
        $translated = str_replace('https://example.org/path', '@@0@@', $source);
        $result = $this->runFixture([
            'format' => 'structured_json', 'input' => $source, 'translated' => $translated, 'filters' => ['url'],
            'variables' => ['response_schema' => [
                'type' => 'object',
                'properties' => ['@@5@@' => ['type' => 'string'], 'url' => ['type' => 'string']],
                'required' => ['@@5@@', 'url'], 'additionalProperties' => false,
            ]],
        ]);
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame($source, $result['result']);
        self::assertSame(1, $result['httpCalls']);
    }

    /** @dataProvider scalarRoots */
    public function testJsonFormatsAcceptScalarRootsWithExactNonStringValues(
        string $format,
        string $input,
        string $translated
    ): void {
        $result = $this->runFixture([
            'format' => $format, 'input' => $input, 'translated' => $translated,
        ]);

        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame($translated, $result['result']);
        self::assertSame(1, $result['httpCalls']);
        self::assertSame("[JSON schema validated]\n", $result['debugResponse']);
    }

    /** @return iterable<string,array{string,string,string}> */
    public static function scalarRoots(): iterable
    {
        foreach (['json', 'linedJson'] as $format) {
            foreach (
                [
                'zero' => ['0', '0'],
                'decimal' => ['1.2300', '1.2300'],
                'true' => ['true', 'true'],
                'false' => ['false', 'false'],
                'null' => ['null', 'null'],
                'string' => ['"Hello"', '"Hallo"'],
                ] as $name => [$input, $translated]
            ) {
                yield $format . '/' . $name => [$format, $input, $translated];
            }
        }
    }

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
    public function jsonFormatsAllowObjectKeysToBeReorderedButKeepListOrder(): void
    {
        $input = '{"meta":{"0":"zero","1":"one","10":"ten","01":"leading",'
            . '"nested":[{"first":"one","second":"two"}]},'
            . '"items":[{"title":"First","details":{"a":"A","b":"B"}},'
            . '{"title":"Second","details":{"a":"C","b":"D"}}],'
            . '"title":"Source"}';
        $translated = '{"title":"Перевод","items":[{"details":{"b":"Б","a":"А"},'
            . '"title":"Первый"},{"details":{"b":"Д","a":"Ц"},"title":"Второй"}],'
            . '"meta":{"01":"leading","10":"ten","1":"one","0":"zero",'
            . '"nested":[{"second":"два","first":"один"}]}}';

        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runFixture([
                'format' => $format, 'input' => $input, 'translated' => $translated,
            ]);

            $this->assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            $this->assertSame($translated, $result['result']);
        }
    }

    /** @test */
    public function jsonFormatsRejectScalarValuesMovedBetweenObjectKeys(): void
    {
        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runFixture([
                'format' => $format,
                'input' => '{"a":1,"b":2,"nested":{"left":true,"right":false}}',
                'translated' => '{"b":1,"a":2,"nested":{"right":true,"left":false}}',
            ]);

            $this->assertSame('RuntimeException', $result['type']);
            $this->assertStringContainsString('JSON scalar values changed', $result['message']);
        }
    }

    /** @test */
    public function jsonFormatsRejectListValuesMovedBetweenIndexes(): void
    {
        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runFixture([
                'format' => $format,
                'input' => '{"values":[1,2]}',
                'translated' => '{"values":[2,1]}',
            ]);

            $this->assertSame('RuntimeException', $result['type']);
            $this->assertStringContainsString('JSON scalar values changed', $result['message']);
        }
    }

    /** @test */
    public function jsonFormatsMatchEquivalentEscapedAndUnicodeObjectKeys(): void
    {
        $result = $this->runFixture([
            'format' => 'json',
            'input' => '{"caf\\u00e9":"Source","nested":{"\\u0061":"A"}}',
            'translated' => '{"nested":{"a":"Перевод"},"caf\\u00e9":"Translation"}',
        ]);

        $this->assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        $this->assertSame('{"nested":{"a":"Перевод"},"caf\\u00e9":"Translation"}', $result['result']);
    }

    /** @test */
    public function jsonFormatsPreserveExactNonStringScalarLexemesWhenKeysMove(): void
    {
        $input = '{"decimal":0.12345678901234567891,"large":9223372036854775808,'
            . '"enabled":true,"empty":null,"nested":{"number":1.0}}';
        $translated = '{"nested":{"number":1.0},"empty":null,"enabled":true,'
            . '"large":9223372036854775808,"decimal":0.12345678901234567891}';

        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runFixture([
                'format' => $format, 'input' => $input, 'translated' => $translated,
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
        $this->assertSame("‹‹TRANSLATION››\n{$translated}\n‹‹END››", $raw['choices'][0]['message']['content']);
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

    public function testJsonFormatsRejectClearedStringsAndAllowDisablingContainerChecks(): void
    {
        foreach (['json', 'linedJson'] as $format) {
            $scenario = [
                'format' => $format,
                'input' => '{"items":[{"title":"Source"}],"empty":""}',
                'translated' => '{"items":[{"title":""}],"empty":""}',
            ];
            $checked = $this->runFixture($scenario);
            self::assertSame(\RuntimeException::class, $checked['type']);
            self::assertStringContainsString('JSON string content lost: $/items/0/title', $checked['message']);
            $unchecked = $this->runFixture($scenario + ['validate' => false]);
            self::assertSame('success', $unchecked['type']);
            self::assertSame($scenario['translated'], $unchecked['result']);
        }
    }

    public function testNativeStructuredJsonRejectsClearedStrings(): void
    {
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash'] as $model) {
            $checked = $this->runFixture([
                'model' => $model,
                'input' => '{"message":"Source"}',
                'translated' => '{"message":""}',
            ], 'structured_output.php');
            self::assertSame(\RuntimeException::class, $checked['type']);
            self::assertStringContainsString('JSON string content lost: $/message', $checked['message']);
        }
    }

    /**
     * @param array<string,mixed> $scenario
     * @return array<string,mixed>
     */
    private function runFixture(array $scenario, string $fixture = 'json_output.php'): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/' . $fixture],
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
