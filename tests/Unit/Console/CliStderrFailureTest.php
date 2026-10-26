<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliStderrFailureTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/bblslug-cli-stderr-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporaryDirectory));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->temporaryDirectory);
    }

    public function testClosedStderrDoesNotCorruptSuccessfulJsonStdout(): void
    {
        $result = $this->runCli(['--source=' . $this->sourceFile()], ['BBLSLUG_CLOSE_STDERR' => '1']);

        self::assertSame(0, $result['exitCode']);
        self::assertSame('{"translated":"Bonjour"}', $result['stdout']);
        self::assertStringNotContainsString('Warning', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testClosedStderrWithVerboseDoesNotLeakDiagnosticsToStdout(): void
    {
        $result = $this->runCli(
            ['--source=' . $this->sourceFile(), '--verbose'],
            ['BBLSLUG_CLOSE_STDERR' => '1']
        );

        self::assertSame(0, $result['exitCode']);
        self::assertSame('{"translated":"Bonjour"}', $result['stdout']);
        self::assertStringNotContainsString('FIXTURE DEBUG', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testSuccessfulFileOutputWithClosedStderrRemainsValid(): void
    {
        $source = $this->sourceFile();
        $destination = $this->temporaryDirectory . '/translated.json';
        $result = $this->runCli(
            ['--source=' . $source, '--translated=' . $destination],
            ['BBLSLUG_CLOSE_STDERR' => '1']
        );

        self::assertSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
        self::assertSame('{"translated":"Bonjour"}', file_get_contents($destination));
    }

    public function testProviderFailureWithClosedStderrPreservesExistingOutput(): void
    {
        $source = $this->sourceFile();
        $destination = $this->temporaryDirectory . '/translated.json';
        file_put_contents($destination, 'existing output');
        $result = $this->runCli(
            ['--source=' . $source, '--translated=' . $destination],
            ['BBLSLUG_CLOSE_STDERR' => '1', 'BBLSLUG_TEST_FAILURE' => '1']
        );

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
        self::assertSame('existing output', file_get_contents($destination));
    }

    public function testNormalStderrStillReceivesStatsAndVerboseDiagnostics(): void
    {
        $result = $this->runCli(['--source=' . $this->sourceFile(), '--verbose']);

        self::assertSame(0, $result['exitCode']);
        self::assertSame('{"translated":"Bonjour"}', $result['stdout']);
        self::assertStringContainsString('FIXTURE DEBUG REQUEST', $result['stderr']);
        self::assertStringContainsString('FIXTURE DEBUG RESPONSE', $result['stderr']);
        self::assertStringContainsString('Characters processed:', $result['stderr']);
        self::assertStringContainsString('Prompt:', $result['stderr']);
        self::assertStringNotContainsString('FIXTURE DEBUG', $result['stdout']);
    }

    public function testBrokenStderrPipeDoesNotLeakWarningsToStdout(): void
    {
        $result = $this->runCli(
            ['--source=' . $this->sourceFile(), '--verbose'],
            [],
            true
        );

        self::assertSame(0, $result['exitCode']);
        self::assertSame('{"translated":"Bonjour"}', $result['stdout']);
        self::assertStringNotContainsString('Warning', $result['stdout']);
    }

    public function testMissingModelWithClosedStderrHasNoStdoutOrWarning(): void
    {
        $result = $this->runCli([], ['BBLSLUG_CLOSE_STDERR' => '1'], includeModel: false);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testDryRunWithClosedStderrWritesPreparedFileWithoutFatalError(): void
    {
        $source = $this->sourceFile();
        $result = $this->runCli(['--source=' . $source, '--dry-run'], ['BBLSLUG_CLOSE_STDERR' => '1']);

        self::assertSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
        self::assertSame('{"source":"Hello"}', file_get_contents($source . '.prepared'));
    }

    private function sourceFile(): string
    {
        $source = $this->temporaryDirectory . '/source.json';
        file_put_contents($source, '{"source":"Hello"}');

        return $source;
    }

    /** @param list<string> $arguments @param array<string,string> $extraEnvironment */
    private function runCli(
        array $arguments,
        array $extraEnvironment = [],
        bool $closeStderrReader = false,
        bool $includeModel = true
    ): array {
        $command = [
            PHP_BINARY,
            '-d',
            'display_errors=1',
            '-d',
            'log_errors=0',
            dirname(__DIR__, 2) . '/Fixtures/console/stderr_failure.php',
            ...($includeModel ? ['--model=openai:gpt-4o'] : []),
            '--format=json',
            ...$arguments,
        ];
        $environment = array_merge(['BBLSLUG_OPENAI_API_KEY' => 'fixture-key'], $extraEnvironment);
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            $environment
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        if ($closeStderrReader) {
            fclose($pipes[2]);
        }
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = $closeStderrReader ? '' : stream_get_contents($pipes[2]);
        if (!$closeStderrReader) {
            fclose($pipes[2]);
        }

        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
