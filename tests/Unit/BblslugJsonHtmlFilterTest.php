<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugJsonHtmlFilterTest extends TestCase
{
    public function testNativeDeepLTranslatesAroundEncodedProtectedHtml(): void
    {
        $html = 'Hello <code title="a > b">Hello "quoted" \\ code</code> World';
        $inner = '{"body":' . json_encode($html, JSON_THROW_ON_ERROR) . ',"id":9223372036854775809}';
        $source = '{"embedded":' . json_encode($inner, JSON_THROW_ON_ERROR) . ',"exponent":1e+20}';
        foreach (['json', 'linedJson'] as $format) {
            $out = $this->fixture('deepl_document', [
                'source' => $source, 'format' => $format, 'filters' => ['html_code'],
            ]);
            self::assertSame('success', $out['type'], $out['message'] ?? '');
            self::assertStringContainsString('Hello @@0@@ World', $out['calls'][0]['body']);
            self::assertStringNotContainsString('quoted', $out['calls'][0]['body']);
            self::assertSame('1e+20', substr($out['result'], -6, 5));
            $outer = json_decode($out['result'], true, 512, JSON_THROW_ON_ERROR);
            $decoded = json_decode($outer['embedded'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(
                'Hallo <code title="a > b">Hello "quoted" \\ code</code> Welt',
                $decoded['body']
            );
            self::assertStringContainsString('9223372036854775809', $outer['embedded']);
        }
    }

    public function testOpenAiResponseRestoresEncodedHtmlWithExactScalarTokens(): void
    {
        $body = 'Hello <code title="a > b">Hello \\ path</code> World';
        $source = '{"body":' . json_encode($body, JSON_THROW_ON_ERROR) . ',"id":9223372036854775809}';
        $out = $this->fixture('json_output', [
            'input' => $source,
            'translated' => '{"body":"Hallo @@0@@ Welt","id":9223372036854775809}',
            'filters' => ['html_code'],
        ]);
        self::assertSame('success', $out['type'], $out['message'] ?? '');
        self::assertSame(
            '{"body":' . json_encode('Hallo <code title="a > b">Hello \\ path</code> Welt', JSON_THROW_ON_ERROR)
                . ',"id":9223372036854775809}',
            $out['result']
        );
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function fixture(string $name, array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/' . $name . '.php'],
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
