<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugCheckpointTest extends TestCase
{
    private string $directory;
    private string $checkpoint;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bblslug-checkpoint-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory, 0700));
        $this->checkpoint = $this->directory . '/state.json';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testResumeAvoidsSuccessfulRequestsAndSeparatesCurrentUsage(): void
    {
        $failed = $this->runFixture(['failAt' => 2, 'failure' => 'http']);
        self::assertSame(\RuntimeException::class, $failed['type']);
        self::assertSame(2, $failed['networkCalls']);
        $r = $this->runFixture(['operationTimeoutSeconds' => 20, 'apiKey' => 'rotated-fixture-key']);
        self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
        self::assertSame("T:one\nT:two\nT:three", $r['result']['result']);
        self::assertSame(2, $r['networkCalls']);
        self::assertSame(1, $r['result']['reusedBatches']);
        self::assertSame([true, false, false], array_column($r['result']['batches'], 'reused'));
        self::assertSame(30, $r['result']['consumed']['tokens']['total']);
        self::assertSame(20, $r['result']['consumedThisRun']['tokens']['total']);
        $raw = json_decode($r['result']['rawResponseBody'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('', $raw[0]);
        self::assertNotSame('', $raw[1]);
        $complete = $this->runFixture();
        self::assertSame('success', $complete['type'], (string) ($complete['message'] ?? ''));
        self::assertSame(0, $complete['networkCalls']);
        self::assertSame(3, $complete['result']['reusedBatches']);
        self::assertSame([], $complete['result']['consumedThisRun']);
        self::assertSame($r['result']['result'], $complete['result']['result']);
        $stored = file_get_contents($this->checkpoint);
        self::assertStringNotContainsString('fixture-key', $stored);
        self::assertStringNotContainsString('rawResponseBody', $stored);
        self::assertStringNotContainsString('debugRequest', $stored);
    }

    /** @dataProvider invalidSecondResponses */
    public function testInvalidSecondResponseIsNotCheckpointed(string $failure): void
    {
        $failed = $this->runFixture(['failAt' => 2, 'failure' => $failure]);
        self::assertSame(\RuntimeException::class, $failed['type']);
        self::assertSame(2, $failed['networkCalls']);
        $state = $this->readEntries();
        self::assertCount(1, $state['records']);
        $firstRecord = json_encode($state['records'][0], JSON_THROW_ON_ERROR);

        $resumed = $this->runFixture();
        self::assertSame('success', $resumed['type'], (string) ($resumed['message'] ?? ''));
        self::assertSame(2, $resumed['networkCalls']);
        self::assertSame(1, $resumed['result']['reusedBatches']);
        self::assertSame(30, $resumed['result']['consumed']['tokens']['total']);
        self::assertSame(20, $resumed['result']['consumedThisRun']['tokens']['total']);
        $completed = $this->readEntries();
        self::assertCount(3, $completed['records']);
        self::assertSame($firstRecord, json_encode($completed['records'][0], JSON_THROW_ON_ERROR));
    }

    public static function invalidSecondResponses(): array
    {
        return [['missing'], ['duplicate'], ['newline'], ['marker']];
    }

    public function testMalformedUsageDoesNotBreakTranslationOrCheckpointResume(): void
    {
        $settings = ['rawUsage' => [
            'total_tokens' => -5, 'prompt_tokens' => 1.5, 'completion_tokens' => str_repeat('9', 40),
        ]];
        foreach ([null, $this->checkpoint] as $path) {
            $r = $this->runFixture($settings + ['checkpointPath' => $path]);
            self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
            self::assertSame("T:one\nT:two\nT:three", $r['result']['result']);
            self::assertSame(0, $r['result']['consumed']['tokens']['total']);
            self::assertSame(0, $r['result']['consumed']['tokens']['breakdown']['prompt']);
            self::assertSame(0, $r['result']['consumed']['tokens']['breakdown']['completion']);
        }
        $resumed = $this->runFixture($settings);
        self::assertSame('success', $resumed['type'], (string) ($resumed['message'] ?? ''));
        self::assertSame(0, $resumed['networkCalls']);
        self::assertSame(0, $resumed['result']['consumed']['tokens']['total']);
    }

    public function testOverflowingCheckpointUsageOmitsTotalButRetainsBreakdownAcrossResume(): void
    {
        foreach ([PHP_INT_MAX, (string) PHP_INT_MAX] as $total) {
            $this->checkpoint = $this->directory . '/state-' . (is_string($total) ? 'string' : 'int') . '.json';
            $settings = ['rawUsage' => [
                'total_tokens' => $total, 'prompt_tokens' => 4, 'completion_tokens' => 6,
            ]];
            $failed = $this->runFixture($settings + ['failAt' => 2, 'failure' => 'http']);
            self::assertSame(\RuntimeException::class, $failed['type']);
            self::assertSame(2, $failed['networkCalls']);

            $resumed = $this->runFixture($settings);
            self::assertSame('success', $resumed['type'], (string) ($resumed['message'] ?? ''));
            self::assertSame(1, $resumed['result']['reusedBatches']);
            self::assertArrayNotHasKey('total', $resumed['result']['consumed']['tokens']);
            self::assertSame(12, $resumed['result']['consumed']['tokens']['breakdown']['prompt']);
            self::assertSame(18, $resumed['result']['consumed']['tokens']['breakdown']['completion']);
            self::assertSame(0, $resumed['result']['consumed']['tokens']['breakdown']['reasoning']);
            self::assertArrayNotHasKey('total', $resumed['result']['consumedThisRun']['tokens']);
            self::assertSame(8, $resumed['result']['consumedThisRun']['tokens']['breakdown']['prompt']);
            self::assertSame(12, $resumed['result']['consumedThisRun']['tokens']['breakdown']['completion']);
            self::assertSame(0, $resumed['result']['consumedThisRun']['tokens']['breakdown']['reasoning']);

            $complete = $this->runFixture($settings);
            self::assertSame('success', $complete['type'], (string) ($complete['message'] ?? ''));
            self::assertSame(0, $complete['networkCalls']);
            self::assertSame([], $complete['result']['consumedThisRun']);
            self::assertArrayNotHasKey('total', $complete['result']['consumed']['tokens']);
            self::assertSame(12, $complete['result']['consumed']['tokens']['breakdown']['prompt']);
            self::assertSame(18, $complete['result']['consumed']['tokens']['breakdown']['completion']);
            self::assertSame(0, $complete['result']['consumed']['tokens']['breakdown']['reasoning']);
        }
    }

    public function testChangedInputModelSettingsOrPacketPlanRejectBeforeHttp(): void
    {
        $this->runFixture(['failAt' => 2, 'failure' => 'missing']);
        $before = file_get_contents($this->checkpoint);
        foreach (
            [
                ['input' => "one\ntwo\nchanged"], ['model' => 'google:gemini-2.5-flash'],
                ['variables' => ['temperature' => 0.2]], ['textSegmentBatchSize' => 2],
                ['context' => 'different context'],
            ] as $settings
        ) {
            $r = $this->runFixture($settings);
            self::assertSame(\RuntimeException::class, $r['type']);
            self::assertStringContainsString('identity mismatch', $r['message']);
            self::assertSame(0, $r['networkCalls']);
            self::assertSame($before, file_get_contents($this->checkpoint));
        }
    }

    public function testLoadedPromptChangesInvalidateCheckpoint(): void
    {
        $prompt = $this->directory . '/prompts.yaml';
        file_put_contents($prompt, "translator:\n  json: Translate all fragments.\n");
        $this->runFixture(['promptFile' => $prompt, 'failAt' => 2, 'failure' => 'http']);
        file_put_contents($prompt, "translator:\n  json: Translate all fragments precisely.\n");
        $r = $this->runFixture(['promptFile' => $prompt]);
        self::assertSame(\RuntimeException::class, $r['type']);
        self::assertStringContainsString('identity mismatch', $r['message']);
        self::assertSame(0, $r['networkCalls']);
    }

    public function testCorruptCachedProtocolFailsBeforeAnyNewRequest(): void
    {
        $this->runFixture(['failAt' => 2, 'failure' => 'http']);
        $state = $this->readEntries();
        $state['records'][0]['text'] = '{}';
        $state['checksums'][0] = hash('sha256', json_encode(
            $state['records'][0],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
        $this->writeEntries($state);
        $r = $this->runFixture();
        self::assertSame(\RuntimeException::class, $r['type']);
        self::assertStringContainsString('ids mismatch', $r['message']);
        self::assertSame(0, $r['networkCalls']);
    }

    public function testLaterCorruptCachedProtocolFailsBeforeAnyNewRequest(): void
    {
        $failed = $this->runFixture(['failAt' => 3, 'failure' => 'http']);
        self::assertSame(\RuntimeException::class, $failed['type']);
        $state = $this->readEntries();
        self::assertCount(2, $state['records']);
        $state['records'][1]['text'] = '{}';
        $state['checksums'][1] = hash('sha256', json_encode(
            $state['records'][1],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
        $this->writeEntries($state);
        $before = file_get_contents($this->checkpoint);

        $result = $this->runFixture();
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('ids mismatch', $result['message']);
        self::assertSame(0, $result['networkCalls']);
        self::assertSame($before, file_get_contents($this->checkpoint));
    }

    public function testSplitHtmlInJsonCanBeFullyReusedWithoutChangingOtherTokens(): void
    {
        $input = '{"meta":900719925474099312345, "body":"<p>One two three four five six seven</p>"}';
        $settings = [
            'format' => 'json', 'input' => $input, 'textTransport' => 'document',
            'htmlTransport' => 'segments', 'htmlJsonPointers' => ['/body'],
            'textSegmentBatchSize' => null, 'segmentBatchMaxBytes' => 30, 'identity' => true,
        ];
        $first = $this->runFixture($settings);
        self::assertSame('success', $first['type'], (string) ($first['message'] ?? ''));
        self::assertSame($input, $first['result']['result']);
        self::assertGreaterThan(1, $first['networkCalls']);
        $second = $this->runFixture($settings);
        self::assertSame('success', $second['type'], (string) ($second['message'] ?? ''));
        self::assertSame(0, $second['networkCalls']);
        self::assertSame($input, $second['result']['result']);
    }

    public function testLockIsHeldThroughValidationAndDoneOnFreshAndReusedBatches(): void
    {
        $settings = [
            'format' => 'html', 'input' => '<p>one</p><p>two</p>',
            'textTransport' => 'document', 'textSegmentBatchSize' => null,
            'htmlTransport' => 'segments', 'htmlSegmentBatchSize' => 1,
            'identity' => true, 'probeLock' => true,
        ];
        $first = $this->runFixture($settings);
        self::assertSame('success', $first['type'], (string) ($first['message'] ?? ''));
        self::assertSame(2, $first['networkCalls']);
        $stored = file_get_contents($this->checkpoint);
        $second = $this->runFixture($settings);
        self::assertSame('success', $second['type'], (string) ($second['message'] ?? ''));
        self::assertSame(0, $second['networkCalls']);
        self::assertSame(2, $second['result']['reusedBatches']);
        self::assertSame(['', ''], json_decode($second['result']['rawResponseBody'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame([], $second['result']['consumedThisRun']);
        self::assertSame($stored, file_get_contents($this->checkpoint));
        foreach ([$first, $second] as $result) {
            self::assertSame($settings['input'], $result['result']['result']);
            self::assertSame([
                ['phase' => 'Post-validation started (html)', 'held' => true],
                ['phase' => 'Done', 'held' => true],
            ], $result['lockProbes']);
            self::assertFalse($result['lockedAfterReturn']);
        }
    }

    public function testValidationFailureReleasesLockAfterKeepingCompletedBatches(): void
    {
        $result = $this->runFixture([
            'format' => 'html', 'input' => '<p>one</p><p>two</p>',
            'textTransport' => 'document', 'textSegmentBatchSize' => null,
            'htmlTransport' => 'segments', 'htmlSegmentBatchSize' => 1,
            'translated' => '{"s0":"~~~","s1":"two"}', 'probeLock' => true,
        ]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('Markup, non-translatable attributes', $result['message']);
        self::assertSame(2, $result['networkCalls']);
        self::assertSame([
            ['phase' => 'Post-validation started (html)', 'held' => true],
        ], $result['lockProbes']);
        self::assertFalse($result['lockedAfterReturn']);
        $state = $this->readEntries();
        self::assertCount(2, $state['records']);
    }

    public function testDryRunAndNoTextDoNotReadOrCreateState(): void
    {
        foreach ([['dryRun' => true], ['input' => " \n@@0@@"]] as $settings) {
            $r = $this->runFixture($settings);
            self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
            self::assertSame(0, $r['networkCalls']);
            self::assertFileDoesNotExist($this->checkpoint);
            self::assertFileDoesNotExist($this->checkpoint . '.lock');
        }
    }

    public function testPartialTailIsRepairedBeforeResume(): void
    {
        $this->runFixture(['failAt' => 2, 'failure' => 'http']);
        $prefix = file_get_contents($this->checkpoint);
        file_put_contents($this->checkpoint, '{"index":1,"record":"partial', FILE_APPEND);
        $result = $this->runFixture();
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame(2, $result['networkCalls']);
        self::assertSame(1, $result['result']['reusedBatches']);
        self::assertStringStartsWith($prefix, file_get_contents($this->checkpoint));
        self::assertStringNotContainsString('"record":"partial', file_get_contents($this->checkpoint));
    }

    /** @dataProvider recoveryFailures */
    public function testTailRecoveryFailureRejectsBeforeHttpAndCanResume(string $fault): void
    {
        $this->runFixture(['failAt' => 2, 'failure' => 'http']);
        $prefix = file_get_contents($this->checkpoint);
        $tail = '{"index":1,"record":"partial';
        file_put_contents($this->checkpoint, $tail, FILE_APPEND);
        $result = $this->runFixture([], 'checkpoint_recovery_failure.php', [$fault]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertSame(0, $result['networkCalls']);
        $after = file_get_contents($this->checkpoint);
        self::assertStringStartsWith($prefix, $after);
        if ($fault === 'ftruncate') {
            self::assertSame($prefix . $tail, $after);
        }
        $resumed = $this->runFixture();
        self::assertSame('success', $resumed['type'], (string) ($resumed['message'] ?? ''));
        self::assertSame(2, $resumed['networkCalls']);
        self::assertSame(1, $resumed['result']['reusedBatches']);
        self::assertStringStartsWith($prefix, file_get_contents($this->checkpoint));
    }

    public static function recoveryFailures(): array
    {
        return [['ftruncate'], ['fflush'], ['fsync']];
    }

    public function testCompleteCorruptRowFailsBeforeHttpWithoutMutation(): void
    {
        $this->runFixture(['failAt' => 2, 'failure' => 'http']);
        file_put_contents($this->checkpoint, "{bad}\n", FILE_APPEND);
        $before = file_get_contents($this->checkpoint);
        $result = $this->runFixture();
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertSame(0, $result['networkCalls']);
        self::assertSame($before, file_get_contents($this->checkpoint));
    }

    public function testReadOnlyCheckpointFailsBeforeHttp(): void
    {
        $this->runFixture(['failAt' => 2, 'failure' => 'http']);
        chmod($this->checkpoint, 0400);
        try {
            $probe = @fopen($this->checkpoint, 'r+b');
            if ($probe !== false) {
                fclose($probe);
                self::markTestSkipped('Filesystem does not enforce read-only file permissions for this user');
            }
            $before = file_get_contents($this->checkpoint);
            $result = $this->runFixture();
            self::assertSame(\RuntimeException::class, $result['type']);
            self::assertSame(0, $result['networkCalls']);
            self::assertSame($before, file_get_contents($this->checkpoint));
        } finally {
            chmod($this->checkpoint, 0600);
        }
    }

    /** @return array{header: array, records: array, checksums: array} */
    private function readEntries(): array
    {
        $lines = file($this->checkpoint, FILE_IGNORE_NEW_LINES);
        $header = json_decode(array_shift($lines), true, 512, JSON_THROW_ON_ERROR);
        $records = [];
        $checksums = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $records[] = $entry['record'];
            $checksums[] = $entry['checksum'];
        }
        return compact('header', 'records', 'checksums');
    }

    private function writeEntries(array $state): void
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        $bytes = json_encode($state['header'], $flags) . "\n";
        foreach ($state['records'] as $index => $record) {
            $bytes .= json_encode([
                'index' => $index, 'record' => $record, 'checksum' => $state['checksums'][$index],
            ], $flags) . "\n";
        }
        file_put_contents($this->checkpoint, $bytes);
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(
        array $scenario = [],
        string $fixture = 'checkpoint.php',
        array $arguments = []
    ): array {
        $scenario += ['checkpointPath' => $this->checkpoint, 'textSegmentBatchSize' => 1];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/' . $fixture, ...$arguments],
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
