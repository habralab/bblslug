<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugTextSegmentBatchesTest extends TestCase
{
    /** @dataProvider providers */
    public function testSequentialTextBatchesPreserveGlobalIdsAndAggregateUsage(string $model, array $variables): void
    {
        $result = $this->runFixture([
            'model' => $model, 'variables' => $variables, 'input' => "one\r\ntwo\nthree\rfour",
            'textSegmentBatchSize' => 2,
        ]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame("T:one\r\nT:two\nT:three\rT:four", $result['result']['result']);
        self::assertSame([['s0' => 'one', 's1' => 'two'], ['s2' => 'three', 's3' => 'four']], $result['packets']);
        self::assertSame(2, $result['networkCalls']);
        if ($model === 'deepl:free') {
            self::assertSame([], $result['result']['consumed']);
        } else {
            self::assertSame(20, $result['result']['consumed']['tokens']['total']);
        }
        self::assertSame([2, 2], array_column($result['result']['batches'], 'segments'));
        self::assertCount(2, json_decode($result['result']['rawResponseBody'], true, 512, JSON_THROW_ON_ERROR));
    }

    public static function providers(): array
    {
        return [
            'OpenAI' => ['openai:gpt-5-mini', []],
            'Google' => ['google:gemini-2.5-flash', []],
            'Yandex' => ['yandex:gpt-lite', ['folder_id' => 'fixture-folder']],
            'DeepL' => ['deepl:free', ['json_transport' => 'text_batch']],
        ];
    }

    public function testGlobalIdsCountFragmentsAndKeepNewlinesAndFilterMarkersLocal(): void
    {
        $result = $this->runFixture([
            'input' => "Hello https://example.org\r\n<code>fixed</code>\nWorld", 'filters' => ['url', 'html_code'],
            'translated' => '{"s0":"Bonjour","s1":"Monde"}', 'textSegmentBatchSize' => 1,
        ]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame("Bonjour https://example.org\r\n<code>fixed</code>\nMonde", $result['result']['result']);
        self::assertSame([['s0' => 'Hello'], ['s1' => 'World']], $result['packets']);
        self::assertSame(2, array_sum(array_column($result['result']['batches'], 'segments')));
    }

    public function testNullBatchSizeRetainsDefaultSingleRequestShape(): void
    {
        $result = $this->runFixture(['textSegmentBatchSize' => null]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame("T:one\nT:two\nT:three", $result['result']['result']);
        self::assertSame(1, $result['networkCalls']);
        self::assertArrayNotHasKey('batches', $result['result']);
        self::assertIsString($result['result']['rawResponseBody']);
    }

    public function testNoTextAndDryRunMakeNoRequestsAndReturnEmptyBatches(): void
    {
        $result = $this->runFixture(['input' => " \t\r\n@@0@@", 'textSegmentBatchSize' => 1]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame(0, $result['networkCalls']);
        self::assertSame([], $result['result']['batches']);
        $result = $this->runFixture(['dryRun' => true, 'textSegmentBatchSize' => 1]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame(0, $result['networkCalls']);
        self::assertCount(3, $result['result']['batches']);
    }

    public function testLaterPacketLengthOrRequestLimitFailsBeforeFirstHttp(): void
    {
        $result = $this->runFixture([
            'model' => 'deepl:free', 'variables' => ['json_transport' => 'text_batch'],
            'input' => "ok\n" . str_repeat('😀', 3000), 'context' => str_repeat('c', 125000),
            'textSegmentBatchSize' => 1,
        ]);
        self::assertSame(\InvalidArgumentException::class, $result['type']);
        self::assertSame(0, $result['networkCalls']);
    }

    public function testLaterPacketLengthFailsBeforeFirstHttp(): void
    {
        $result = $this->runFixture([
            'model' => 'deepl:free', 'input' => "ok\n" . str_repeat('x', 31000),
            'textSegmentBatchSize' => 1,
        ]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertSame(0, $result['networkCalls']);
        self::assertStringContainsString('batch 2/2', $result['message']);
    }

    /** @dataProvider failures */
    public function testSecondPacketFailureStopsWithoutPartialResult(string $failure): void
    {
        $result = $this->runFixture([
            'failAt' => 2, 'failure' => $failure, 'textSegmentBatchSize' => 1, 'validate' => false,
        ]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertSame(2, $result['networkCalls']);
        self::assertArrayNotHasKey('result', $result);
        self::assertStringContainsString('Text segment batch 2/3 failed; 1 earlier', $result['message']);
        if ($failure === 'truncation') {
            self::assertStringContainsString('truncated', $result['message']);
        }
    }

    public static function failures(): array
    {
        return [
            ['missing'], ['extra'], ['duplicate'], ['type'], ['empty'], ['newline'], ['marker'], ['http'],
            ['truncation'],
        ];
    }

    public function testSingleOversizedFragmentFailsBeforeHttp(): void
    {
        $result = $this->runFixture([
            'model' => 'deepl:free', 'variables' => ['json_transport' => 'text_batch'],
            'input' => str_repeat('x', 31000), 'textSegmentBatchSize' => 1,
        ]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertSame(0, $result['networkCalls']);
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/text_segment_batches.php'],
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
