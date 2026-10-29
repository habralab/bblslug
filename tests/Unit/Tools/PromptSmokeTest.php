<?php

declare(strict_types=1);

namespace Bblslug\Tests\Tools;

use PHPUnit\Framework\TestCase;

final class PromptSmokeTest extends TestCase
{
    public function testWorkerCasesAndFailuresWithoutHttp(): void
    {
        $log = sys_get_temp_dir() . '/bblslug-prompt-log-' . bin2hex(random_bytes(5));
        $run = static function (string $case, string $failure = '') use ($log): array {
            $environment = ['PATH' => getenv('PATH') ?: '', 'BBLSLUG_OPENAI_API_KEY' => 'secret-test-key',
                'PROMPT_TEST_LOG' => $log, 'PROMPT_TEST_FAILURE' => $failure];
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/tools/prompt_worker.php', $case],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                $environment
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            return [proc_close($process), $stdout, $stderr];
        };
        foreach (['context', 'group', 'custom'] as $case) {
            [$exit, $stdout, $stderr] = $run($case);
            self::assertSame(0, $exit, $stderr);
            self::assertSame('passed', json_decode($stdout, true)['status']);
        }
        $calls = array_map(static fn ($line) => json_decode($line, true), file($log, FILE_IGNORE_NEW_LINES));
        self::assertCount(4, $calls);
        self::assertSame($calls[0]['pid'], $calls[1]['pid']);
        self::assertStringContainsString('Arbeitsbereich', $calls[0]['context']);
        self::assertStringContainsString('Projektbereich', $calls[1]['context']);
        self::assertSame('experimental_xml', $calls[2]['prompt']);
        self::assertSame('app_owned', $calls[3]['prompt']);
        self::assertStringContainsString('formal German', $calls[3]['context']);
        self::assertStringNotContainsString('Projektbereich', $calls[3]['context']);
        foreach (['json', 'structured_json'] as $format) {
            self::assertStringContainsString('Projektbereich', $calls[3]['custom'][$format]);
        }
        foreach (['term', 'exception'] as $failure) {
            [$exit, $stdout, $stderr] = $run('group', $failure);
            self::assertNotSame(0, $exit);
            self::assertStringNotContainsString('secret-test-key', $stdout . $stderr);
            self::assertSame('response_or_assertion', $stderr);
        }
        unlink($log);
    }

    public function testPreflightAndUnsupportedModelsNeverNeedCredentials(): void
    {
        $directory = sys_get_temp_dir() . '/bblslug-prompt-test-' . bin2hex(random_bytes(5));
        $base = ['PATH' => getenv('PATH') ?: '', 'BBLSLUG_SMOKE_SUITE' => 'prompts',
            'BBLSLUG_SMOKE_REPORT_DIR' => $directory, 'BBLSLUG_PROXY' => 'http://127.0.0.1:1'];
        $run = static function (array $env): int {
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__, 3) . '/tools/live-smoke.php'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                $env
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            return proc_close($process);
        };
        self::assertNotSame(0, $run($base + ['BBLSLUG_SMOKE_OFFLINE' => '1', 'BBLSLUG_SMOKE_LIVE' => '1',
            'BBLSLUG_SMOKE_MODELS' => 'openai:gpt-6-luna']));
        self::assertDirectoryDoesNotExist($directory);
        self::assertNotSame(0, $run($base + ['BBLSLUG_SMOKE_LIVE' => '1']));
        self::assertDirectoryDoesNotExist($directory);
        self::assertNotSame(0, $run($base + ['BBLSLUG_SMOKE_LIVE' => '1',
            'BBLSLUG_SMOKE_MODELS' => 'openai:gpt-6-luna,deepl:free,unknown']));
        $rows = json_decode(file_get_contents($directory . '/summary.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['missing_key', 'unsupported_deepl', 'unknown_model'], array_column($rows, 'status'));
        self::assertSame(0700, fileperms($directory) & 0777);
        unlink($directory . '/summary.json');
        rmdir($directory);
    }
}
