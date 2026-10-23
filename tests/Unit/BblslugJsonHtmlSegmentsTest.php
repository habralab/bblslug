<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugJsonHtmlSegmentsTest extends TestCase
{
    public function testProvidersTranslateOnlySelectedHtmlAndKeepOtherFieldsByteExact(): void
    {
        $source = '{"metadata" : "DO NOT SEND", "price":1.2300, "big":900719925474099312345,'
            . '"body":"<p>Hello <code>fixed</code><a href=\"https://example.com\">world</a></p>",'
            . '"tail":"\\u003Cunselected\\u003E"}';
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash', 'deepl:free'] as $model) {
            foreach (['json', 'linedJson'] as $format) {
                $result = $this->runFixture([
                    'model' => $model, 'format' => $format, 'input' => $source,
                    'translated' => str_starts_with($model, 'deepl:')
                        ? '{"s0":"Bonjour","s1":"monde"}' : '{"s1":"monde","s0":"Bonjour"}',
                    'htmlJsonPointers' => ['/body'], 'filters' => ['url', 'html_code'],
                    'variables' => str_starts_with($model, 'deepl:') ? ['json_transport' => 'text_batch'] : [],
                ]);
                self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
                self::assertSame(str_replace(['Hello', '>world<'], ['Bonjour', '>monde<'], $source), $result['result']);
                self::assertSame(1, $result['networkCalls']);
                $request = json_encode($result['request'], JSON_THROW_ON_ERROR);
                self::assertStringNotContainsString('DO NOT SEND', $request);
                self::assertStringNotContainsString('example.com', $request);
                self::assertStringNotContainsString('fixed', $request);
            }
        }
    }

    public function testFieldScopedFiltersDoNotChangeSourceKeysOrUnselectedHtml(): void
    {
        $source = '{"@@0@@":"<p>Hello <code>fixed</code></p>", "outside":"<code>unclosed"}';
        $result = $this->runFixture([
            'format' => 'json', 'input' => $source,
            'htmlJsonPointers' => ['/@@0@@'], 'filters' => ['html_code'],
        ]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame(str_replace('Hello', 'Bonjour', $source), $result['result']);
        self::assertSame([['filter' => 'html_code', 'count' => 1]], $result['filterStats']);
    }

    public function testDryRunAndNoTextRestoreOriginalJsonWhileReturningFieldScopedPreparedJson(): void
    {
        $source = '{"keep" : "<code>untouched", "body":"<div>Hello <code>fixed</code></div>"}';
        foreach (
            [
            ['dryRun' => true, 'filters' => ['html_code']],
            ['filters' => ['html_div']],
            ] as $options
        ) {
            $result = $this->runFixture(array_merge([
                'format' => 'json', 'input' => $source, 'htmlJsonPointers' => ['/body'],
            ], $options));
            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame($source, $result['result']);
            self::assertNotSame($source, $result['prepared']);
            self::assertStringContainsString('"keep" : "<code>untouched"', $result['prepared']);
            self::assertSame(0, $result['networkCalls']);
        }
    }

    public function testProtocolChecksRemainEnabledWithoutContainerValidation(): void
    {
        $result = $this->runFixture([
            'format' => 'json', 'htmlJsonPointers' => ['/body'], 'validate' => false,
            'input' => '{"body":"<p>Hello</p><p>World</p>"}', 'translated' => '{"s0":"Bonjour"}',
        ]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertSame(1, $result['networkCalls']);
    }

    public function testInvalidSelectionsAndIncompatibleSchemasFailBeforeHttp(): void
    {
        foreach (
            [
            ['htmlJsonPointers' => []],
            ['htmlJsonPointers' => ['/missing']],
            ['htmlJsonPointers' => ['/count']],
            ['htmlJsonPointers' => ['body']],
            ['htmlTransport' => 'document'],
            ['format' => 'html'],
            ['format' => 'structured_json'],
            ['variables' => ['response_schema' => ['type' => 'object']]],
            ] as $options
        ) {
            $result = $this->runFixture(array_merge([
                'format' => 'json', 'input' => '{"body":"<p>Hello</p>","count":1}',
                'htmlJsonPointers' => ['/body'],
            ], $options));
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
