<?php

declare(strict_types=1);

namespace Bblslug\Tests\Tools;

use PHPUnit\Framework\TestCase;

final class SmokeRunnerTest extends TestCase
{
    private array $directories = [];

    public function testOfflineRunnerCoversActualInterruptDeclineResumeAndCache(): void
    {
        $directory = $this->directory();
        $result = $this->runTool(['BBLSLUG_SMOKE_SUITE' => 'segments', 'BBLSLUG_SMOKE_OFFLINE' => '1',
            'BBLSLUG_SMOKE_CASES' => 'large', 'BBLSLUG_SMOKE_FORMATS' => 'text',
            'BBLSLUG_SMOKE_REPORT_DIR' => $directory]);
        self::assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        $rows = json_decode(file_get_contents($directory . '/summary.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $rows);
        self::assertSame('passed', $rows[0]['status']);
        foreach (
            ['apiInterrupted', 'controlledDeclinePreservedState', 'cliResumedOnlyPending',
            'completedCacheNoHttp', 'currentRunUsage', 'allMarkersPreserved', 'sourceRejectedBeforeHttp',
            'capRejectedBeforeHttp', 'hintsRejectedBeforeHttp', 'checksumRejectedBeforeHttp'] as $check
        ) {
            self::assertTrue($rows[0]['checks'][$check], $check);
        }
        $manifest = json_decode(file_get_contents($directory . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($manifest['offline']);
        self::assertNotEmpty($manifest['codeHashes']);
    }

    public function testLargePaidSuiteRequiresAnExplicitModeBeforeDoingWork(): void
    {
        $directory = $this->directory();
        $r = $this->runTool(['BBLSLUG_SMOKE_SUITE' => 'segments', 'BBLSLUG_SMOKE_REPORT_DIR' => $directory]);
        self::assertNotSame(0, $r['exit']);
        self::assertStringContainsString('requires BBLSLUG_SMOKE_OFFLINE=1 or BBLSLUG_SMOKE_LIVE=1', $r['stderr']);
        self::assertFileDoesNotExist($directory . '/manifest.json');
        $r = $this->runTool(['BBLSLUG_SMOKE_OFFLINE' => '1', 'BBLSLUG_SMOKE_REPORT_DIR' => $directory]);
        self::assertNotSame(0, $r['exit']);
        self::assertStringContainsString('Offline mode requires', $r['stderr']);
        self::assertFileDoesNotExist($directory . '/manifest.json');
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/bblslug-suite-test-' . bin2hex(random_bytes(5));
        $this->directories[] = $directory;
        return $directory;
    }

    private function runTool(array $settings): array
    {
        $environment = ['PATH' => getenv('PATH') ?: '', 'BBLSLUG_PROXY' => 'http://127.0.0.1:1'];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 3) . '/tools/live-smoke.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            $settings + $environment
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    protected function tearDown(): void
    {
        $remove = static function (string $path) use (&$remove): void {
            if (is_dir($path)) {
                foreach (scandir($path) ?: [] as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        $remove($path . '/' . $entry);
                    }
                }
                rmdir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        };
        foreach ($this->directories as $directory) {
            $remove($directory);
        }
    }
}
