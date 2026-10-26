<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class LiveTestIsolationTest extends TestCase
{
    /** @return array<string,array{string}> */
    public static function disabledFlags(): array
    {
        return ['empty' => [''], 'zero' => ['0'], 'false' => ['false'], 'off' => ['off'], 'true' => ['true']];
    }

    /** @dataProvider disabledFlags */
    public function testDisabledLiveSuiteDoesNotPrintSecretsOrPerformHttp(string $flag): void
    {
        $report = tempnam(sys_get_temp_dir(), 'bblslug-live-junit-');
        self::assertNotFalse($report);
        try {
            [$code, $stdout, $stderr] = $this->runProcess($flag, [
                '--configuration=' . dirname(__DIR__, 2) . '/phpunit.xml',
                '--testsuite=integration', '--verbose', '--colors=never', '--log-junit=' . $report,
            ]);
            self::assertSame(0, $code, $stderr);
            self::assertStringContainsString('Skipped: 3', $stdout);
            self::assertStringNotContainsString('Unexpected live HTTP request', $stdout . $stderr);
            $output = $stdout . $stderr . file_get_contents($report);
            foreach ($this->secrets() as $secret) {
                self::assertStringNotContainsString($secret, $output);
            }
        } finally {
            unlink($report);
        }
    }

    public function testEnabledProviderContainsOnlyModelMetadata(): void
    {
        [$code, $stdout, $stderr] = $this->runProcess('1', ['--provider-only']);
        self::assertSame(0, $code, $stderr);
        $cases = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $cases);
        foreach ($cases as $case) {
            self::assertCount(2, $case);
            self::assertContains($case[0], ['openai', 'yandex']);
            self::assertStringStartsWith($case[0] . ':', $case[1]);
        }
        foreach ($this->secrets() as $secret) {
            self::assertStringNotContainsString($secret, $stdout . $stderr);
        }
    }

    public function testFailedLiveDiagnosticAssertionsDoNotPrintTheirSecretNeedle(): void
    {
        $report = tempnam(sys_get_temp_dir(), 'bblslug-live-junit-');
        self::assertNotFalse($report);
        try {
            [$code, $stdout, $stderr] = $this->runProcess('1', [
                '--configuration=' . dirname(__DIR__, 2) . '/phpunit.xml',
                '--testsuite=integration', '--verbose', '--colors=never', '--log-junit=' . $report,
            ], true);
            self::assertSame(1, $code, $stderr);
            self::assertStringContainsString('Failures: 3', $stdout);
            self::assertStringContainsString('API key leaked in request diagnostics', $stdout);
            foreach ($this->secrets() as $secret) {
                self::assertStringNotContainsString($secret, $stdout . $stderr . file_get_contents($report));
            }
        } finally {
            unlink($report);
        }
    }

    /** @return list<string> */
    private function secrets(): array
    {
        return ['fixture-openai-private', 'fixture-yandex-private', 'fixture-folder-private'];
    }

    /** @param list<string> $args @return array{int,string,string} */
    private function runProcess(string $flag, array $args, bool $leakDiagnostics = false): array
    {
        [$openai, $yandex, $folder] = $this->secrets();
        $process = proc_open([
            PHP_BINARY, dirname(__DIR__) . '/Fixtures/integration/live_runner.php', ...$args,
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), [
            'RUN_LIVE_TESTS' => $flag,
            'BBLSLUG_OPENAI_API_KEY' => $openai,
            'BBLSLUG_YANDEX_API_KEY' => $yandex,
            'BBLSLUG_YANDEX_FOLDER_ID' => $folder,
            'BBLSLUG_LIVE_MODEL' => $leakDiagnostics ? 'openai:gpt-5-mini' : '',
            'BBLSLUG_TEST_LEAK_DIAGNOSTICS' => $leakDiagnostics ? '1' : '0',
        ]);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), (string) $stdout, (string) $stderr];
    }
}
