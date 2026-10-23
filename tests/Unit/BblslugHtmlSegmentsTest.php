<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugHtmlSegmentsTest extends TestCase
{
    public function testProviderJsonTransportsReconstructHtmlAndKeepRawResponses(): void
    {
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash', 'deepl:free'] as $model) {
            $result = $this->runFixture([
                'model' => $model,
                'input' => '<DIV class="keep"> Hello <em>world</em> </DIV>',
                'translated' => '{"s0":"Bonjour","s1":"monde"}',
                'variables' => str_starts_with($model, 'deepl:') ? ['json_transport' => 'text_batch'] : [],
            ]);
            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame('<DIV class="keep"> Bonjour <em>monde</em> </DIV>', $result['result']);
            self::assertSame(1, $result['networkCalls']);
            self::assertStringNotContainsString('<DIV', json_encode($result['request'], JSON_THROW_ON_ERROR));
            self::assertNotSame($result['result'], $result['rawResponseBody']);
        }
    }

    public function testFiltersRestoreLocallyWithoutSendingProtectedCodeOrUrls(): void
    {
        $source = '<p>Hello <code>fixed</code><a href="https://example.com/path">World</a></p>';
        $result = $this->runFixture([
            'input' => $source,
            'filters' => ['url', 'html_code'],
            'translated' => '{"s0":"Bonjour","s1":"Monde"}',
        ]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame(str_replace(['Hello', 'World'], ['Bonjour', 'Monde'], $source), $result['result']);
        $request = json_encode($result['request'], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('fixed', $request);
        self::assertStringNotContainsString('example.com', $request);
    }

    public function testMissingSegmentsAreRejectedEvenWithContainerValidationDisabled(): void
    {
        foreach ([true, false] as $validate) {
            $result = $this->runFixture([
                'input' => '<p>Hello</p><p>World</p>', 'translated' => '{"s0":"Bonjour"}', 'validate' => $validate,
            ]);
            self::assertSame(\RuntimeException::class, $result['type']);
            self::assertSame(1, $result['networkCalls']);
        }
    }

    public function testNoTextAndDryRunAvoidNetworkAndReturnPreparedHtml(): void
    {
        foreach (
            [
            ['input' => '<!-- keep --><script>fixed()</script><code>fixed</code>'],
            ['input' => '<p>Hello</p>', 'dryRun' => true],
            ] as $scenario
        ) {
            $result = $this->runFixture($scenario);
            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame($scenario['input'], $result['result']);
            self::assertSame(0, $result['networkCalls']);
        }
    }

    public function testInvalidModeAndFormatFailBeforeNetwork(): void
    {
        foreach ([['htmlTransport' => 'unknown'], ['format' => 'text']] as $scenario) {
            $result = $this->runFixture($scenario);
            self::assertSame(\InvalidArgumentException::class, $result['type']);
            self::assertSame(0, $result['networkCalls']);
        }
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/html_segments.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            []
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
