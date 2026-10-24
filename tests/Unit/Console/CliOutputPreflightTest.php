<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliOutputPreflightTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/bblslug-cli-preflight-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporaryDirectory));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testBadOutputPathsAreRejectedBeforeAnyProviderRequest(): void
    {
        $source = $this->sourceFile();
        $counter = $this->counterFile();
        $cases = [
            'directory' => [$this->temporaryDirectory . '/destination', 'directory'],
            'missing parent' => [$this->temporaryDirectory . '/missing/destination.txt', 'parent'],
        ];
        mkdir($cases['directory'][0]);

        foreach ($cases as [$destination, $message]) {
            file_put_contents($counter, '0');
            $result = $this->runSegmentCli(
                ['--source=' . $source, '--translated=' . $destination],
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );

            self::assertNotSame(0, $result['exitCode'], $message);
            self::assertSame('0', file_get_contents($counter), $message);
            self::assertStringContainsString('Failed to write', $result['stderr'], $message);
            self::assertStringNotContainsString('Translation complete', $result['stderr'], $message);
        }
    }

    public function testNonWritableExistingFileIsRejectedBeforeProviderRequest(): void
    {
        $source = $this->sourceFile();
        $destination = $this->temporaryDirectory . '/readonly.txt';
        $counter = $this->counterFile();
        file_put_contents($destination, 'unchanged');
        chmod($destination, 0444);
        if (is_writable($destination)) {
            chmod($destination, 0644);
            self::markTestSkipped('The test user can write chmod 0444 files.');
        }

        try {
            file_put_contents($counter, '0');
            $result = $this->runSegmentCli(
                ['--source=' . $source, '--translated=' . $destination],
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('0', file_get_contents($counter));
            self::assertSame('unchanged', file_get_contents($destination));
        } finally {
            chmod($destination, 0644);
        }
    }

    public function testNonWritableParentForNewFileIsRejectedBeforeProviderRequest(): void
    {
        $source = $this->sourceFile();
        $parent = $this->temporaryDirectory . '/readonly';
        $destination = $parent . '/new.txt';
        $counter = $this->counterFile();
        mkdir($parent);
        chmod($parent, 0555);
        if (is_writable($parent)) {
            chmod($parent, 0755);
            self::markTestSkipped('The test user can write chmod 0555 directories.');
        }

        try {
            file_put_contents($counter, '0');
            $result = $this->runSegmentCli(
                ['--source=' . $source, '--translated=' . $destination],
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('0', file_get_contents($counter));
            self::assertFileDoesNotExist($destination);
        } finally {
            chmod($parent, 0755);
        }
    }

    public function testTranslatedOptionMissingValueIsNotConsumedFromNextOption(): void
    {
        $source = $this->sourceFile();
        $counter = $this->counterFile();
        $result = $this->runSegmentCli(
            ['--source=' . $source, '--translated', '--model=openai:gpt-5-mini'],
            ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
        );

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('0', file_get_contents($counter));
        self::assertStringContainsString('translated', strtolower($result['stderr']));
        self::assertStringNotContainsString('Cannot read source file', $result['stderr']);
    }

    public function testTranslatedOptionEmptyAndRepeatedValuesAreRejected(): void
    {
        $source = $this->sourceFile();
        $counter = $this->counterFile();
        foreach (
            [
                ['--translated='],
                [
                    '--translated=' . $this->temporaryDirectory . '/one',
                    '--translated=' . $this->temporaryDirectory . '/two',
                ],
            ] as $options
        ) {
            file_put_contents($counter, '0');
            $result = $this->runSegmentCli(
                ['--source=' . $source, ...$options],
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );
            self::assertNotSame(0, $result['exitCode'], implode(' ', $options));
            self::assertSame('', $result['stdout'], implode(' ', $options));
            self::assertSame('0', file_get_contents($counter), implode(' ', $options));
        }
    }

    public function testOptionLookingValuesFollowingContextRemainContextValues(): void
    {
        $source = $this->sourceFile('Hello');
        $counter = $this->counterFile();
        $literalCheckpoint = $this->temporaryDirectory . '/literal-checkpoint';
        self::assertFileDoesNotExist($literalCheckpoint);

        foreach ([['--context', '--translated='], ['--context', '--checkpoint=' . $literalCheckpoint]] as $options) {
            file_put_contents($counter, '0');
            $result = $this->runSegmentCli(
                ['--source=' . $source, ...$options],
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );

            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertSame('1', file_get_contents($counter));
            self::assertSame('Bonjour', $result['stdout']);
            self::assertFileDoesNotExist($literalCheckpoint);
        }
    }

    public function testValidExistingFileIsReplacedAndSourceIsUnchanged(): void
    {
        $source = $this->sourceFile('Hello');
        $destination = $this->temporaryDirectory . '/translated.txt';
        $counter = $this->counterFile();
        file_put_contents($destination, 'old output');
        file_put_contents($counter, '0');

        $result = $this->runSegmentCli(
            ['--source=' . $source, '--translated=' . $destination],
            ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
        );

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('1', file_get_contents($counter));
        self::assertSame('Bonjour', file_get_contents($destination));
        self::assertSame('Hello', file_get_contents($source));
    }

    public function testWritableExistingFileDoesNotRequireWritableParent(): void
    {
        $source = $this->sourceFile('Hello');
        $parent = $this->temporaryDirectory . '/readonly-parent';
        $destination = $parent . '/translated.txt';
        $counter = $this->counterFile();
        mkdir($parent);
        file_put_contents($destination, 'old output');
        chmod($parent, 0555);
        if (is_writable($parent)) {
            chmod($parent, 0755);
            self::markTestSkipped('The test user can write chmod 0555 directories.');
        }

        try {
            file_put_contents($counter, '0');
            $result = $this->runSegmentCli(
                ['--source=' . $source, '--translated=' . $destination],
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );
            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertSame('1', file_get_contents($counter));
            self::assertSame('Bonjour', file_get_contents($destination));
        } finally {
            @unlink($destination);
            chmod($parent, 0755);
        }
    }

    public function testDefaultAndPhpStdoutOutputsRemainExact(): void
    {
        $source = $this->sourceFile('Hello');
        foreach ([[], ['--translated=php://stdout']] as $options) {
            $result = $this->runSegmentCli(['--source=' . $source, ...$options]);

            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertSame('Bonjour', $result['stdout']);
        }
    }

    public function testDryRunIgnoresInvalidTranslatedPathAndDoesNotRequest(): void
    {
        $source = $this->sourceFile('Hello');
        $counter = $this->counterFile();
        $invalidDestination = $this->temporaryDirectory . '/missing-parent/output.txt';
        file_put_contents($counter, '0');

        $result = $this->runSegmentCli(
            ['--source=' . $source, '--dry-run', '--translated=' . $invalidDestination],
            ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
        );

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('0', file_get_contents($counter));
        self::assertSame('', $result['stdout']);
        self::assertSame('Hello', file_get_contents($source . '.prepared'));
    }

    public function testExistingSymlinkToWritableFileIsWrittenThrough(): void
    {
        $source = $this->sourceFile('Hello');
        $target = $this->temporaryDirectory . '/real.txt';
        $link = $this->temporaryDirectory . '/translated-link.txt';
        $counter = $this->counterFile();
        file_put_contents($target, 'old output');
        self::assertTrue(symlink($target, $link));
        file_put_contents($counter, '0');

        $result = $this->runSegmentCli(
            ['--source=' . $source, '--translated=' . $link],
            ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
        );

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('1', file_get_contents($counter));
        self::assertSame('Bonjour', file_get_contents($target));
        self::assertSame('Bonjour', file_get_contents($link));
    }

    public function testProviderFailurePreservesExistingDestinationAndDoesNotCreateNewOne(): void
    {
        $source = $this->sourceFile('Hello');
        $existing = $this->temporaryDirectory . '/existing.txt';
        $new = $this->temporaryDirectory . '/new.txt';
        file_put_contents($existing, 'sentinel');

        $failedExisting = $this->runFailureCli($source, $existing);
        $failedNew = $this->runFailureCli($source, $new);

        self::assertNotSame(0, $failedExisting['exitCode']);
        self::assertNotSame(0, $failedNew['exitCode']);
        self::assertSame('sentinel', file_get_contents($existing));
        self::assertFileDoesNotExist($new);
    }

    /** @return array{exitCode:int,stdout:string,stderr:string} */
    private function runSegmentCli(array $options = [], array $extraEnvironment = []): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/Fixtures/console/segment_batches.php',
            '--model=openai:gpt-5-mini', '--format=text', '--text-transport=segments',
            '--text-segment-batch-size=1', '--no-validate', ...$options,
        ];
        return $this->runProcess($command, ['BBLSLUG_OPENAI_API_KEY' => 'fake-test-key'] + $extraEnvironment);
    }

    /** @return array{exitCode:int,stdout:string,stderr:string} */
    private function runFailureCli(string $source, string $destination): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/Fixtures/console/provider_failure.php',
            '--model=openai:gpt-5-mini', '--format=text', '--source=' . $source,
            '--translated=' . $destination,
        ];
        return $this->runProcess($command, [
            'BBLSLUG_OPENAI_API_KEY' => 'fake-test-key',
            'BBLSLUG_TEST_CASE' => 'http401',
        ]);
    }

    /** @param list<string> $command @param array<string,string> $environment */
    private function runProcess(array $command, array $environment): array
    {
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            $environment
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        ];
    }

    private function sourceFile(string $contents = 'Hello'): string
    {
        $source = $this->temporaryDirectory . '/source.txt';
        file_put_contents($source, $contents);

        return $source;
    }

    private function counterFile(): string
    {
        $counter = $this->temporaryDirectory . '/counter.txt';
        file_put_contents($counter, '0');

        return $counter;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) {
            @unlink($directory);
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @chmod($directory, 0755);
        @rmdir($directory);
    }
}
