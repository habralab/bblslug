<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugDeepLHtmlTest extends TestCase
{
    private const SOURCE = '<div class="wrap"><p>Hi <strong>there</strong>!</p>'
        . '<a href="https://example.org/docs?a=1&amp;b=2">Documentation</a>'
        . '<pre><code>echo "a\\b";</code></pre></div>';

    public function testProtectedCodeStaysOutsideTheLinkAndInternalWrappersDoNotLeak(): void
    {
        $result = $this->runFixture('translate');
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame(
            str_replace(['Hi', 'there', 'Documentation'], ['Hallo', 'da', 'Dokumentation'], self::SOURCE),
            $result['result']
        );
        self::assertStringNotContainsString('data-bblslug-marker', $result['result']);
    }

    public function testLiteralMarkersAndSourceSpansWithSimilarAttributesSurvive(): void
    {
        $source = '<div>Hi <span translate="no" data-bblslug-marker="source-0">@@0@@</span>'
            . '<pre><code>echo "a\\b";</code></pre></div>';
        $result = $this->runFixture('translate', $source);
        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame(str_replace('Hi', 'Hallo', $source), $result['result']);
    }

    public function testMovedProtectedBlockIsStillRejectedAfterWrapperRemoval(): void
    {
        $result = $this->runFixture('move');
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('HTML structure mismatch', $result['message']);
    }

    public function testDroppedAndDuplicatedTransportWrappersAreRejected(): void
    {
        foreach (['drop', 'duplicate'] as $mutation) {
            $result = $this->runFixture($mutation);
            self::assertSame(\RuntimeException::class, $result['type']);
            self::assertStringContainsString('DeepL HTML placeholder', $result['message']);
        }
    }

    /** @return array<string,mixed> */
    private function runFixture(string $mutation, string $source = self::SOURCE): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/deepl_json.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode([
            'source' => $source, 'format' => 'html',
            'filters' => ['url', 'html_pre'], 'htmlMutation' => $mutation,
        ], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
