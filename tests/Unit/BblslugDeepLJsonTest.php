<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugDeepLJsonTest extends TestCase
{
    public function testFacadeRestoresBatchValuesIntoOriginalJsonAndEncodedContainers(): void
    {
        $source = '{"title":"Hello","id":9223372036854775809,"on":true,"optional":null,'
            . '"embedded":"{\"label\":\"World\",\"decimal\":0.12345678901234567891}"}';
        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runFixture([
                'source' => $source, 'format' => $format, 'translations' => ['Hallo', 'Welt'],
            ]);
            self::assertSame('success', $result['type'], $result['message'] ?? '');
            self::assertSame(['Hello', 'World'], $result['texts']);
            self::assertSame(1, $result['calls']);
            self::assertSame(str_replace(['Hello', 'World'], ['Hallo', 'Welt'], $source), $result['result']);
        }
    }

    public function testQuotesBackslashesAndNewlinesAreEncodedAfterTranslation(): void
    {
        $translated = "Hallo \"quoted\" \\\\ path\nnext";
        $result = $this->runFixture(['source' => '{"label":"Hello"}', 'translations' => [$translated]]);
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame($translated, json_decode($result['result'], true, 512, JSON_THROW_ON_ERROR)['label']);
    }

    public function testNumericAndEmptyContainersReturnLocallyWithoutAProviderCall(): void
    {
        foreach (['{}', '[]', '{"id":123,"on":true,"optional":null,"blank":" "}'] as $source) {
            $result = $this->runFixture(['source' => $source]);
            self::assertSame('success', $result['type'], $result['message'] ?? '');
            self::assertSame($source, $result['result']);
            self::assertSame(0, $result['calls']);
            self::assertSame(0, $result['httpStatus']);
        }
    }

    public function testEscapedHtmlUrlsAndSourceMarkersSurviveBothFilterOrders(): void
    {
        $source = json_encode([
            'label' => 'Hello @@0@@',
            'embedded' => json_encode([
                'html' => '<code>https://example.org/path</code> and https://example.org/docs',
                'id' => 123,
            ], JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);
        foreach ([['url', 'html_code'], ['html_code', 'url']] as $filters) {
            $result = $this->runFixture(['source' => $source, 'filters' => $filters]);
            self::assertSame('success', $result['type'], $result['message'] ?? '');
            self::assertSame($source, $result['result']);
        }
    }

    public function testIncompleteBatchIsRejectedByTheFacade(): void
    {
        $result = $this->runFixture(['source' => '["Hello","World"]', 'translations' => ['Hallo']]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('count mismatch', $result['message']);
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/deepl_json.php'],
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
        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
