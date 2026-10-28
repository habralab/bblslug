<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;

final class ReadOnlyInstallationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bblslug-readonly-' . bin2hex(random_bytes(5));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                chmod($entry->getPathname(), 0700);
            }
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                chmod($entry->getPathname(), 0700);
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testWritableCheckpointWorksWithReadOnlyInstallation(): void
    {
        $root = dirname(__DIR__, 3);
        $installation = $this->directory . '/installation';
        mkdir($installation, 0700);
        foreach (['src', 'resources'] as $tree) {
            $this->copyTree($root . '/' . $tree, $installation . '/' . $tree);
        }
        chmod($installation, 0555);
        $before = $this->snapshot($installation);
        $checkpoint = $this->directory . '/checkpoint';
        $scenario = ['input' => "one\ntwo", 'textSegmentBatchSize' => 1, 'checkpointPath' => $checkpoint];
        $fresh = $this->runFixture($installation, $scenario);
        self::assertSame('success', $fresh['type'], (string) ($fresh['message'] ?? ''));
        self::assertSame(realpath($installation . '/src/Bblslug/Bblslug.php'), $fresh['libraryPath']);
        self::assertSame(2, $fresh['networkCalls']);
        $state = file_get_contents($checkpoint);
        $resumed = $this->runFixture($installation, $scenario);
        self::assertSame('success', $resumed['type'], (string) ($resumed['message'] ?? ''));
        self::assertSame($fresh['libraryPath'], $resumed['libraryPath']);
        self::assertSame(0, $resumed['networkCalls']);
        self::assertSame($fresh['result']['result'], $resumed['result']['result']);
        self::assertEquals($fresh['result']['consumed'], $resumed['result']['consumed']);
        self::assertSame([], $resumed['result']['consumedThisRun']);
        self::assertSame($state, file_get_contents($checkpoint));
        self::assertSame($before, $this->snapshot($installation));
    }

    private function copyTree(string $source, string $destination): void
    {
        mkdir($destination, 0700);
        foreach (new \DirectoryIterator($source) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            $target = $destination . '/' . $entry->getFilename();
            if ($entry->isDir()) {
                $this->copyTree($entry->getPathname(), $target);
            } else {
                copy($entry->getPathname(), $target);
                chmod($target, 0444);
            }
        }
        chmod($destination, 0555);
    }

    private function snapshot(string $root): array
    {
        $result = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            $result[substr($path, strlen($root))] = $entry->isDir() ? 'directory' : hash_file('sha256', $path);
        }
        ksort($result);
        return $result;
    }

    private function runFixture(string $installation, array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/translation/readonly_installation.php', $installation],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $installation
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
