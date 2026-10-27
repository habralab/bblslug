<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use Bblslug\Console\OutputFileWriter;
use PHPUnit\Framework\TestCase;

final class OutputTargetPreflightTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bblslug-output-preflight-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory));
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $this->directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                foreach (scandir($path) ?: [] as $child) {
                    if ($child !== '.' && $child !== '..') {
                        @unlink($path . '/' . $child);
                    }
                }
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($this->directory);
    }

    public function testValidExistingAndNewTargetsAreNotOpenedOrCreated(): void
    {
        $existing = $this->directory . '/existing.txt';
        $new = $this->directory . '/new.txt';
        self::assertSame(6, file_put_contents($existing, 'stored'));
        $before = scandir($this->directory);
        $inode = fileinode($existing);
        $mode = fileperms($existing);
        touch($existing, 1000000000);

        OutputFileWriter::validateTarget($existing);
        OutputFileWriter::validateTarget($new);

        clearstatcache(true, $existing);
        self::assertSame('stored', file_get_contents($existing));
        self::assertSame($inode, fileinode($existing));
        self::assertSame($mode, fileperms($existing));
        self::assertSame(1000000000, filemtime($existing));
        self::assertFileDoesNotExist($new);
        self::assertSame($before, scandir($this->directory));
    }

    public function testInvalidTargetsAreRejectedWithoutChangingExistingPaths(): void
    {
        $directoryTarget = $this->directory . '/directory';
        $trailingTarget = $this->directory . '/existing.txt';
        $missingParentTarget = $this->directory . '/missing/output.txt';
        self::assertTrue(mkdir($directoryTarget));
        self::assertSame(6, file_put_contents($trailingTarget, 'stored'));
        $before = scandir($this->directory);

        foreach ([$directoryTarget, $trailingTarget . '/', $missingParentTarget, "bad\0path"] as $path) {
            $this->assertRejected($path);
        }

        self::assertSame('stored', file_get_contents($trailingTarget));
        self::assertDirectoryExists($directoryTarget);
        self::assertDirectoryDoesNotExist(dirname($missingParentTarget));
        self::assertSame($before, scandir($this->directory));
    }

    public function testSymlinkAndHardlinkTargetsAreNotChanged(): void
    {
        $target = $this->directory . '/target.txt';
        $symlink = $this->directory . '/symlink.txt';
        $hardlink = $this->directory . '/hardlink.txt';
        self::assertSame(6, file_put_contents($target, 'stored'));

        if (@symlink($target, $symlink)) {
            OutputFileWriter::validateTarget($symlink);
            self::assertTrue(is_link($symlink));
            self::assertSame('stored', file_get_contents($target));
        }

        if (@link($target, $hardlink)) {
            OutputFileWriter::validateTarget($hardlink);
            self::assertSame('stored', file_get_contents($target));
            self::assertSame('stored', file_get_contents($hardlink));
            self::assertSame(fileinode($target), fileinode($hardlink));
        }

        if (!is_link($symlink) && !file_exists($hardlink)) {
            self::markTestSkipped('Neither symlink nor hardlink creation is available.');
        }
    }

    public function testStreamTargetDoesNotWriteDuringPreflight(): void
    {
        ob_start();
        try {
            OutputFileWriter::validateTarget('php://output');
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame('', $output);
    }

    private function assertRejected(string $path): void
    {
        try {
            OutputFileWriter::validateTarget($path);
            self::fail('Invalid output target should be rejected.');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }
    }
}
