<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliAtomicOutputTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bblslug-cli-atomic-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testSuccessfulTranslationStillWritesTheFixtureResult(): void
    {
        $source = $this->directory . '/source.txt';
        $destination = $this->directory . '/translated.txt';
        file_put_contents($source, 'source text');

        $result = $this->runCli(['--source=' . $source, '--translated=' . $destination]);

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('', $result['stdout']);
        self::assertSame('translated text', file_get_contents($destination));
    }

    public function testFsizeFailurePreservesExistingDestinationAndCleansTemporaryFile(): void
    {
        $source = $this->directory . '/source.txt';
        $destination = $this->directory . '/translated.txt';
        file_put_contents($source, 'source text');
        file_put_contents($destination, 'preexisting output');
        $before = scandir($this->directory) ?: [];

        $result = $this->runLimitedCli(['--source=' . $source, '--translated=' . $destination]);
        $this->skipIfUnsupportedLimit($result['exitCode']);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString($destination, $result['stderr']);
        self::assertSame('preexisting output', file_get_contents($destination));
        self::assertSame([], $this->temporaryFiles($destination, $before));
    }

    public function testFsizeFailureDoesNotCreateNewDestination(): void
    {
        $source = $this->directory . '/source.txt';
        $destination = $this->directory . '/new-output.txt';
        file_put_contents($source, 'source text');
        $before = scandir($this->directory) ?: [];

        $result = $this->runLimitedCli(['--source=' . $source, '--translated=' . $destination]);
        $this->skipIfUnsupportedLimit($result['exitCode']);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString($destination, $result['stderr']);
        self::assertFileDoesNotExist($destination);
        self::assertSame([], $this->temporaryFiles($destination, $before));
    }

    public function testDryRunFsizeFailurePreservesExistingPreparedFile(): void
    {
        $source = $this->directory . '/source.txt';
        $prepared = $source . '.prepared';
        file_put_contents($source, 'source text');
        file_put_contents($prepared, 'preexisting prepared output');
        $before = scandir($this->directory) ?: [];

        $result = $this->runLimitedCli(['--source=' . $source, '--dry-run']);
        $this->skipIfUnsupportedLimit($result['exitCode']);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString($prepared, $result['stderr']);
        self::assertSame('preexisting prepared output', file_get_contents($prepared));
        self::assertSame([], $this->temporaryFiles($prepared, $before));
    }

    public function testFsizeFailureThroughSymlinkPreservesLinkAndTarget(): void
    {
        $source = $this->directory . '/source.txt';
        $target = $this->directory . '/real-output.txt';
        $link = $this->directory . '/translated-link.txt';
        file_put_contents($source, 'source text');
        file_put_contents($target, 'preexisting output');
        self::assertTrue(symlink($target, $link));
        $before = scandir($this->directory) ?: [];

        $result = $this->runLimitedCli(['--source=' . $source, '--translated=' . $link]);
        $this->skipIfUnsupportedLimit($result['exitCode']);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertTrue(is_link($link));
        self::assertSame('preexisting output', file_get_contents($target));
        self::assertSame([], $this->temporaryFiles($link, $before));
    }

    public function testTrailingSeparatorIsRejectedBeforeProviderRequest(): void
    {
        $source = $this->directory . '/source.txt';
        $target = $this->directory . '/output.txt';
        $counter = $this->directory . '/counter.txt';
        file_put_contents($source, 'Hello');
        file_put_contents($target, 'old');
        file_put_contents($counter, '0');

        $result = $this->runProcess([
            PHP_BINARY,
            dirname(__DIR__, 2) . '/Fixtures/console/segment_batches.php',
            '--model=openai:gpt-5-mini', '--format=text', '--source=' . $source,
            '--translated=' . $target . '/', '--text-transport=segments', '--text-segment-batch-size=1',
        ], ['BBLSLUG_TEST_COUNTER_FILE' => $counter]);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertSame('0', file_get_contents($counter));
        self::assertSame('old', file_get_contents($target));
    }

    /** @param list<string> $arguments */
    private function runCli(array $arguments): array
    {
        return $this->runProcess([
            PHP_BINARY,
            dirname(__DIR__, 2) . '/Fixtures/console/successful_translation.php',
            '--model=openai:gpt-4o', '--format=text', ...$arguments,
        ]);
    }

    /** @param list<string> $arguments */
    private function runLimitedCli(array $arguments): array
    {
        $script = $this->directory . '/limit.php';
        file_put_contents($script, <<<'PHP'
<?php
declare(strict_types=1);
if (!defined('POSIX_RLIMIT_FSIZE') || !function_exists('posix_setrlimit')
    || !function_exists('pcntl_signal') || !function_exists('pcntl_exec')) {
    exit(77);
}
pcntl_signal(SIGXFSZ, SIG_IGN);
if (!posix_setrlimit(POSIX_RLIMIT_FSIZE, 4, 4)) {
    exit(77);
}
$fixture = $argv[1];
pcntl_exec(PHP_BINARY, array_merge([$fixture], array_slice($argv, 2)));
exit(1);
PHP);

        return $this->runProcess([
            PHP_BINARY, $script,
            dirname(__DIR__, 2) . '/Fixtures/console/successful_translation.php',
            '--model=openai:gpt-4o', '--format=text', ...$arguments,
        ]);
    }

    /** @param list<string> $command @param array<string,string> $environment @return array{exitCode:int,stdout:string,stderr:string} */
    private function runProcess(array $command, array $environment = []): array
    {
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            ['BBLSLUG_OPENAI_API_KEY' => 'fake-test-key'] + $environment
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }

    private function skipIfUnsupportedLimit(int $exitCode): void
    {
        if ($exitCode === 77) {
            self::markTestSkipped('POSIX file-size limits are unavailable.');
        }
    }

    /** @return list<string> */
    private function temporaryFiles(string $destination, array $before = []): array
    {
        $after = scandir(dirname($destination)) ?: [];
        $new = array_diff($after, $before);
        return array_values(array_filter(
            $new,
            static fn (string $name): bool => $name !== basename($destination) && $name !== 'limit.php'
        ));
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
            $path = $directory . '/' . $entry;
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
