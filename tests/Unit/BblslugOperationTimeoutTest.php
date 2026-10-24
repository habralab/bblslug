<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugOperationTimeoutTest extends TestCase
{
    public function testBudgetSpansPacketsAndShrinksEachRequestTimeout(): void
    {
        $r = $this->runFixture([
            'operationTimeoutSeconds' => 1, 'textSegmentBatchSize' => 1, 'delayMilliseconds' => 600,
        ]);
        self::assertSame(\RuntimeException::class, $r['type']);
        self::assertStringContainsString('operation timeout', $r['message']);
        self::assertStringContainsString('batch 2/3 failed; 1 earlier', $r['message']);
        self::assertSame(2, $r['networkCalls']);
        self::assertArrayNotHasKey('result', $r);
        self::assertGreaterThan(0, $r['timeouts'][1]);
        self::assertLessThan($r['timeouts'][0], $r['timeouts'][1]);
    }

    public function testPreparationTimeCountsAndLateResultIsRejected(): void
    {
        foreach (['Checking model length limits', 'Done'] as $message) {
            $r = $this->runFixture(['operationTimeoutSeconds' => 1, 'delayAtFeedback' => $message]);
            self::assertSame(\RuntimeException::class, $r['type']);
            self::assertStringContainsString('operation timeout', $r['message']);
            self::assertSame($message === 'Done' ? 1 : 0, $r['networkCalls']);
            self::assertArrayNotHasKey('result', $r);
        }
    }

    public function testSingleDocumentModeAlsoHasAnOperationBudget(): void
    {
        $r = $this->runFixture([
            'textTransport' => 'document', 'operationTimeoutSeconds' => 1, 'delayMilliseconds' => 1100,
        ]);
        self::assertSame(\RuntimeException::class, $r['type']);
        self::assertStringContainsString('operation timeout', $r['message']);
        self::assertSame(1, $r['networkCalls']);
    }

    public function testDeepLUploadAndPollingShareTheSameBudget(): void
    {
        $r = $this->runFixture([
            'fixture' => 'deepl_document', 'source' => '{"greeting":"Hello"}',
            'operationTimeoutSeconds' => 1, 'delayMilliseconds' => [1 => 600, 2 => 600],
        ]);
        self::assertSame(\RuntimeException::class, $r['type']);
        self::assertStringContainsString('operation timeout', $r['message']);
        self::assertCount(2, $r['calls']);
        self::assertLessThan($r['calls'][0]['timeoutMilliseconds'], $r['calls'][1]['timeoutMilliseconds']);
        self::assertStringNotContainsString('fixture-document-key-secret', $r['message']);
    }

    public function testDisabledAndDryRunBudgetsKeepExistingBehavior(): void
    {
        foreach ([[], ['operationTimeoutSeconds' => 10], ['operationTimeoutSeconds' => 1, 'dryRun' => true]] as $s) {
            $r = $this->runFixture($s + ['textSegmentBatchSize' => 1]);
            self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
            self::assertSame(isset($s['dryRun']) ? 0 : 3, $r['networkCalls']);
        }
        $r = $this->runFixture(['operationTimeoutSeconds' => 0]);
        self::assertSame(\InvalidArgumentException::class, $r['type']);
        self::assertSame(0, $r['networkCalls']);
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/'
                . ($scenario['fixture'] ?? 'operation_timeout') . '.php'],
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
