<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

class CliOutputTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/bblslug-cli-output-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') as $path) {
            if (is_dir($path)) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($this->temporaryDirectory);
    }

    public function testTranslationWritesOutputFile(): void
    {
        $source = $this->createSourceFile();
        $destination = $this->temporaryDirectory . '/translated.txt';

        $result = $this->runCli('--source=' . $source, '--translated=' . $destination);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertStringContainsString('Translation complete: ' . $destination, $result['stderr']);
        $this->assertSame('translated text', file_get_contents($destination));
    }

    public function testTranslationReportsDestinationDirectoryWriteFailure(): void
    {
        $source = $this->createSourceFile();
        $destination = $this->temporaryDirectory . '/destination';
        mkdir($destination);

        $result = $this->runCli('--source=' . $source, '--translated=' . $destination);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertStringContainsString('Failed to write', $result['stderr']);
        $this->assertStringNotContainsString('Translation complete', $result['stderr']);
    }

    public function testDryRunWritesPreparedFile(): void
    {
        $source = $this->createSourceFile();

        $result = $this->runCli('--source=' . $source, '--dry-run');

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertSame('source text', file_get_contents($source . '.prepared'));
        $this->assertStringContainsString('Dry-run: saved prepared file', $result['stderr']);
    }

    public function testDryRunReportsPreparedFileWriteFailure(): void
    {
        $source = $this->createSourceFile();
        mkdir($source . '.prepared');

        $result = $this->runCli('--source=' . $source, '--dry-run');

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertStringContainsString('Failed to write', $result['stderr']);
        $this->assertStringNotContainsString('Dry-run: saved prepared file', $result['stderr']);
    }

    private function createSourceFile(): string
    {
        $source = $this->temporaryDirectory . '/source.txt';
        file_put_contents($source, 'source text');

        return $source;
    }

    /** @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(string ...$arguments): array
    {
        $fixture = dirname(__DIR__, 2) . '/Fixtures/console/successful_translation.php';
        $command = [PHP_BINARY, $fixture, '--model=openai:gpt-4o', '--format=text', ...$arguments];
        $environment = ['BBLSLUG_OPENAI_API_KEY' => 'fake-test-key'];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, dirname(__DIR__, 3), $environment);

        $this->assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return ['exitCode' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
