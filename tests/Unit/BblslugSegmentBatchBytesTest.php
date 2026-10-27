<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugSegmentBatchBytesTest extends TestCase
{
    public function testByteCapSplitsLongTextAndPreservesSourceWhitespace(): void
    {
        $input = "  One two\tthree four five six seven eight nine ten\r\nNext line\n";
        $r = $this->runFixture(['input' => $input, 'segmentBatchMaxBytes' => 32, 'identity' => true]);
        self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
        self::assertSame($input, $r['result']['result']);
        self::assertGreaterThan(2, $r['networkCalls']);
        foreach ($r['packets'] as $packet) {
            self::assertLessThanOrEqual(32, strlen(json_encode((object) $packet, JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
        }
        self::assertCount($r['networkCalls'], $r['result']['batches']);
    }

    public function testOversizedRepeatedProseGetsIndependentlyRequiredSentenceUnits(): void
    {
        $sentence = 'The maintenance team checks the backup.';
        $input = implode(" \t", array_fill(0, 29, $sentence));
        $r = $this->runFixture([
            'model' => 'yandex:gpt-pro', 'variables' => ['folder_id' => 'fixture-folder'],
            'input' => $input, 'segmentBatchMaxBytes' => 180, 'identity' => true,
        ]);
        self::assertSame('success', $r['type'], $r['message'] ?? '');
        self::assertSame($input, $r['result']['result']);
        $units = 0;
        foreach ($r['packets'] as $packet) {
            foreach ($packet as $value) {
                self::assertSame($sentence, $value);
                ++$units;
            }
        }
        self::assertSame(29, $units);
    }

    public function testCountCapCombinesWithBytesAcrossNativeProviders(): void
    {
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash', 'yandex:gpt-pro'] as $model) {
            $r = $this->runFixture([
                'model' => $model, 'input' => "One two three four five six\nSeven eight nine ten",
                'variables' => str_starts_with($model, 'yandex:') ? ['folder_id' => 'fixture'] : [],
                'segmentBatchMaxBytes' => 30, 'textSegmentBatchSize' => 1,
            ]);
            self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
            self::assertGreaterThan(2, $r['networkCalls']);
            self::assertStringStartsWith('T:One', $r['result']['result']);
            foreach ($r['packets'] as $packet) {
                self::assertCount(1, $packet);
            }
        }
    }

    public function testHtmlAndSelectedJsonHtmlRestoreThroughTheSamePlan(): void
    {
        $html = '<p title="One two three four five">Six seven eight nine ten &amp; more</p><code>fixed</code>';
        $json = '{"meta" : 900719925474099312345, "body":'
            . json_encode($html, JSON_THROW_ON_ERROR) . '}';
        foreach ([['html', $html, []], ['json', $json, ['/body']]] as [$format, $input, $pointers]) {
            $r = $this->runFixture([
                'format' => $format, 'input' => $input, 'textTransport' => 'document',
                'htmlTransport' => 'segments', 'htmlJsonPointers' => $pointers,
                'segmentBatchMaxBytes' => 30, 'identity' => true,
            ]);
            self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
            self::assertSame($input, $r['result']['result']);
            self::assertGreaterThan(2, $r['networkCalls']);
        }
    }

    public function testUnbreakableLaterFragmentFailsBeforeFirstHttp(): void
    {
        $r = $this->runFixture(['input' => "ok\n" . str_repeat('😀', 30), 'segmentBatchMaxBytes' => 30]);
        self::assertSame(\InvalidArgumentException::class, $r['type']);
        self::assertSame(0, $r['networkCalls']);
        self::assertArrayNotHasKey('result', $r);
    }

    public function testFailureInSplitFragmentStopsBeforeNextPacket(): void
    {
        foreach (['missing', 'duplicate', 'newline', 'http'] as $failure) {
            $r = $this->runFixture([
                'input' => 'One two three four five six seven eight nine ten eleven twelve',
                'segmentBatchMaxBytes' => 25, 'failAt' => 2, 'failure' => $failure, 'validate' => false,
            ]);
            self::assertSame(\RuntimeException::class, $r['type']);
            self::assertSame(2, $r['networkCalls']);
            self::assertArrayNotHasKey('result', $r);
            self::assertStringContainsString('Text segment batch 2/', $r['message']);
        }
    }

    public function testAbbreviationsReachAllSegmentTransports(): void
    {
        foreach (['text', 'html', 'json'] as $format) {
            $text = 'Dr. Smith met Prof. Jones. Another reviewer agreed. ';
            $prose = str_repeat($text, 4);
            $input = $format === 'text' ? $prose : '<p>' . $prose . '</p>';
            if ($format === 'json') {
                $input = '{"meta":1.2300,"body":' . json_encode($input, JSON_THROW_ON_ERROR) . '}';
            }
            $r = $this->runFixture([
                'format' => $format, 'input' => $input, 'identity' => true,
                'textTransport' => $format === 'text' ? 'segments' : 'document',
                'htmlTransport' => $format === 'text' ? 'document' : 'segments',
                'htmlJsonPointers' => $format === 'json' ? ['/body'] : [],
                'segmentBatchMaxBytes' => 100, 'segmentAbbreviations' => ['Dr.', 'Prof.'],
            ]);
            self::assertSame('success', $r['type'], $r['message'] ?? '');
            self::assertSame($input, $r['result']['result']);
            foreach ($r['packets'] as $packet) {
                foreach ($packet as $value) {
                    self::assertNotSame('Dr.', trim($value));
                    self::assertNotSame('Prof.', trim($value));
                }
            }
        }
        $r = $this->runFixture(['segmentAbbreviations' => ['Dr.']]);
        self::assertSame(\InvalidArgumentException::class, $r['type']);
        self::assertSame(0, $r['networkCalls']);
        $r = $this->runFixture(['variables' => ['segmentAbbreviations' => ['Dr.']]]);
        self::assertSame(\InvalidArgumentException::class, $r['type']);
        self::assertSame(0, $r['networkCalls']);
    }

    public function testNoTextDryRunAndInvalidOptionsAvoidHttp(): void
    {
        foreach ([['input' => "\r\n@@0@@"], ['input' => 'One two three four five', 'dryRun' => true]] as $scenario) {
            $r = $this->runFixture($scenario + ['segmentBatchMaxBytes' => 25]);
            self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
            self::assertSame($scenario['input'], $r['result']['result']);
            self::assertSame(0, $r['networkCalls']);
        }
        foreach ([['segmentBatchMaxBytes' => 0], ['segmentBatchMaxBytes' => 30, 'textTransport' => 'document']] as $s) {
            $r = $this->runFixture($s);
            self::assertSame(\InvalidArgumentException::class, $r['type']);
            self::assertSame(0, $r['networkCalls']);
        }
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/segment_batch_bytes.php'],
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
