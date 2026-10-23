<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugYandexHtmlFenceTest extends TestCase
{
    private const SOURCE = '<div class="wrap"><p>Hello</p>'
        . '<a href="https://example.org/docs?a=1&amp;b=2">Documentation</a>'
        . '<pre><code>echo "a\\b";</code></pre></div>';

    public function testProviderFenceIsRemovedAfterProtectedValuesAreRestored(): void
    {
        $result = $this->runFixture(['input' => self::SOURCE]);

        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame(self::SOURCE, $result['result']);
        self::assertStringContainsString('```', $result['rawResponseBody']);
        self::assertStringNotContainsString('```', $result['result']);
        self::assertStringNotContainsString('data-bblslug-marker', $result['result']);
    }

    public function testChangedMarkupRemainsRejected(): void
    {
        $result = $this->runFixture(['input' => self::SOURCE, 'mutation' => 'drop_tag']);

        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('HTML structure mismatch', $result['message']);
    }

    public function testMissingPlaceholderRemainsRejected(): void
    {
        $result = $this->runFixture(['input' => self::SOURCE, 'mutation' => 'missing_placeholder']);

        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('placeholder', strtolower($result['message']));
    }

    public function testSourceBoundaryFencesAreRetained(): void
    {
        $source = "```html\n<p>Hello</p>\n```";
        $result = $this->runFixture(['input' => $source, 'prefix' => '']);

        self::assertSame('success', $result['type'], $result['message'] ?? '');
        self::assertSame($source, $result['result']);
    }

    public function testUnsupportedFenceFailsBeforeApplicationOutputEvenWithoutValidation(): void
    {
        $result = $this->runFixture([
            'input' => '<p>Hello</p>',
            'validate' => false,
            'response' => "```json\n<p>Hallo</p>",
        ]);

        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('fence', strtolower($result['message']));
    }

    /** @param array<string,mixed> $scenario */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/yandex_html_fence.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2)
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result, $stdout);
        return $result;
    }
}
