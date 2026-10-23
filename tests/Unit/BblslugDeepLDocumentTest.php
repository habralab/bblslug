<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugDeepLDocumentTest extends TestCase
{
    public function testJsonAndLinedJsonUseNativeDocumentWorkflowAndRestoreValues(): void
    {
        $source = '{"title":"Hello","nested":"{\\"label\\":\\"World\\"}","id":7}';
        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runFixture(['source' => $source, 'format' => $format]);
            self::assertSame('success', $result['type'], $result['message'] ?? '');
            self::assertSame('{"title":"Hallo","nested":"{\\"label\\":\\"Welt\\"}","id":7}', $result['result']);
            self::assertCount(3, $result['calls']);
            self::assertStringContainsString('/v2/document', $result['calls'][0]['url']);
            self::assertStringContainsString('/result', $result['calls'][2]['url']);
        }
    }

    public function testCliStyleDocumentTimeoutStringReachesNativeWorkflow(): void
    {
        $result = $this->runFixture([
            'source' => '{"title":"Hello"}',
            'variables' => ['document_timeout' => '5'],
        ]);
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame(5, $result['calls'][0]['timeoutSeconds']);
        self::assertSame(5, $result['calls'][1]['timeoutSeconds']);
        self::assertSame(5, $result['calls'][2]['timeoutSeconds']);
    }

    public function testWindowsPathAndNestedEncodedJsonRoundTripByteForByteWithoutFilters(): void
    {
        $path = 'C:\\tmp\\"x"';
        $source = json_encode([
            'path' => $path,
            'nested' => json_encode(['path' => $path], JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);
        $result = $this->runFixture(['source' => $source]);
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame($source, $result['result']);
    }

    public function testMultipartRequestPreservesSourceAndFiltersAndPropagatesAuthAndProxy(): void
    {
        $source = '{"label":"Hello @@0@@","html":"<p>World</p>",'
            . '"path":"https://example.org/a/b","encodedPath":"https://example.org/a%2Fb"}';
        $result = $this->runFixture([
            'source' => $source,
            'filters' => ['url', 'html_pre'],
            'proxy' => 'http://proxy.test:8080',
        ]);
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame(
            '{"label":"Hallo @@0@@","html":"<p>Welt</p>",'
            . '"path":"https://example.org/a/b","encodedPath":"https://example.org/a%2Fb"}',
            $result['result']
        );
        $upload = $result['calls'][0];
        self::assertStringContainsString('multipart/form-data; boundary=', implode(';', $upload['headers']));
        self::assertStringContainsString('fixture-api-secret', implode('\n', $upload['headers']));
        self::assertSame('http://proxy.test:8080', $upload['proxy']);
        self::assertStringContainsString('translation.json', $upload['body']);
        self::assertStringContainsString('@@3@@', $upload['body']);
        self::assertStringNotContainsString('data-bblslug-marker', $result['result']);
        foreach ($result['calls'] as $call) {
            self::assertSame('http://proxy.test:8080', $call['proxy']);
            self::assertStringContainsString('fixture-api-secret', implode("\n", $call['maskPatterns']));
            self::assertStringContainsString(
                'Authorization: DeepL-Auth-Key fixture-api-secret',
                implode("\n", $call['headers'])
            );
        }
        foreach (['debugRequest', 'debugResponse'] as $log) {
            self::assertStringNotContainsString('fixture-upload-id', $result[$log]);
            self::assertStringNotContainsString('fixture-document-key-secret', $result[$log]);
            self::assertStringNotContainsString('fixture-api-secret', $result[$log]);
        }
    }

    public function testInvalidDownloadedJsonSchemaScalarEmbeddedAndPlaceholderResponsesAreRejected(): void
    {
        $cases = [
            ['downloadRaw' => '{', 'needle' => 'not valid json'],
            ['downloadRaw' => '{"label":3}', 'needle' => 'schema'],
            ['downloadRaw' => '{"label":"Hallo","id":8}', 'needle' => 'json scalar'],
            ['downloadRaw' => '3', 'needle' => 'schema'],
        ];
        foreach ($cases as $case) {
            $source = str_contains($case['downloadRaw'], 'id')
                ? '{"label":"Hello","id":7}'
                : '{"label":"Hello"}';
            $result = $this->runFixture(array_merge(['source' => $source], $case));
            self::assertContains($result['type'], [\RuntimeException::class, \JsonException::class]);
            $firstLine = strtolower(strtok($result['message'], "\n"));
            self::assertTrue(
                str_contains($firstLine, $case['needle'])
                || ($case['needle'] === 'not valid json' && str_contains($firstLine, 'syntax error')),
                $firstLine
            );
        }
        $result = $this->runFixture([
            'source' => '{"embedded":"{\\"label\\":\\"Hello\\"}"}',
            'downloadRaw' => '{"embedded":"{bad"}',
        ]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('embedded', strtolower(strtok($result['message'], "\n")));
        $result = $this->runFixture([
            'source' => '{"label":"Hello @@0@@"}',
            'downloadRaw' => '{"label":"Hallo"}',
            'filters' => ['url'],
        ]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('placeholder', strtolower($result['message']));
    }

    public function testHttpFailuresAndDebugLogsNeverExposeDocumentCredentials(): void
    {
        foreach ([1 => 'upload', 2 => 'poll', 3 => 'download'] as $call => $kind) {
            $result = $this->runFixture([
                'source' => '{"label":"Hello"}',
                'failure' => [
                    'call' => $call,
                    'status' => 503,
                    'body' => 'fixture-upload-id fixture-document-key-secret',
                ],
            ]);
            self::assertSame(\RuntimeException::class, $result['type'], $kind);
            self::assertStringNotContainsString('fixture-upload-id', $result['message']);
            self::assertStringNotContainsString('fixture-document-key-secret', $result['message']);
            self::assertStringNotContainsString('fixture-api-secret', $result['message']);
        }
    }

    public function testDryRunReturnsOriginalAndMarksRequestUnsent(): void
    {
        $source = '{"label":"Hello"}';
        $result = $this->runFixture(['source' => $source, 'dryRun' => true]);
        self::assertSame('success', $result['type']);
        self::assertSame($source, $result['result']);
        self::assertNotEmpty($result['calls']);
        self::assertTrue($result['calls'][0]['dryRun']);
    }

    public function testOnlyNonStringValuesAndEmptyStringsReturnLocally(): void
    {
        foreach (['{"id":1,"enabled":true,"value":null}', '{"empty":""}', '{}', '[]'] as $source) {
            $result = $this->runFixture(['source' => $source]);
            self::assertSame('success', $result['type'], $result['message'] ?? '');
            self::assertSame($source, $result['result']);
            self::assertCount(1, $result['calls']);
            self::assertTrue($result['calls'][0]['dryRun']);
        }
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/deepl_document.php'],
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
        self::assertSame(0, proc_close($process), (string)$stderr);
        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
