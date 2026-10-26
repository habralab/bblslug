<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use Bblslug\Console\OutputFileWriter;
use PHPUnit\Framework\TestCase;

final class OutputFileWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bblslug-output-writer-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testExistingRegularFileIsReplacedWithoutChangingItsMode(): void
    {
        $path = $this->directory . '/output.txt';
        self::assertSame(3, file_put_contents($path, 'old'));
        chmod($path, 0613);

        OutputFileWriter::write($path, 'new contents');

        self::assertSame('new contents', file_get_contents($path));
        self::assertSame(0613, fileperms($path) & 0777);
    }

    public function testNewRegularFileUsesUmaskMasked0666Mode(): void
    {
        $path = $this->directory . '/new.txt';
        $oldUmask = umask(0027);
        try {
            OutputFileWriter::write($path, 'new');
        } finally {
            umask($oldUmask);
        }

        self::assertSame('new', file_get_contents($path));
        self::assertSame(0640, fileperms($path) & 0777);
    }

    public function testFileUriUsesTheCheckedDirectWriteCompatibilityPath(): void
    {
        $path = $this->directory . '/file-uri.txt';
        file_put_contents($path, 'old');

        OutputFileWriter::write('file://' . $path, 'new');

        self::assertSame('new', file_get_contents($path));
    }

    public function testExistingSymlinkIsWrittenThroughAndRemainsASymlink(): void
    {
        $target = $this->directory . '/target.txt';
        $link = $this->directory . '/link.txt';
        file_put_contents($target, 'old');
        self::assertTrue(symlink($target, $link));

        OutputFileWriter::write($link, 'new');

        self::assertTrue(is_link($link));
        self::assertSame('new', file_get_contents($target));
    }

    public function testHardlinkAliasesBothSeeTheSuccessfulDirectWrite(): void
    {
        $path = $this->directory . '/output.txt';
        $alias = $this->directory . '/alias.txt';
        file_put_contents($path, 'old');
        self::assertTrue(link($path, $alias));

        OutputFileWriter::write($path, 'new');

        self::assertSame('new', file_get_contents($path));
        self::assertSame('new', file_get_contents($alias));
        self::assertSame(fileinode($path), fileinode($alias));
    }

    public function testWritableExistingFileCanBeUpdatedWhenItsParentIsReadonly(): void
    {
        $parent = $this->directory . '/readonly';
        self::assertTrue(mkdir($parent));
        $path = $parent . '/output.txt';
        file_put_contents($path, 'old');
        chmod($parent, 0555);
        if (is_writable($parent)) {
            chmod($parent, 0755);
            self::markTestSkipped('The test user can write a chmod 0555 directory.');
        }

        try {
            OutputFileWriter::write($path, 'new');
            self::assertSame('new', file_get_contents($path));
        } finally {
            chmod($parent, 0755);
        }
    }

    public function testDanglingSymlinkIsWrittenThroughWhenItsTargetParentExists(): void
    {
        $target = $this->directory . '/subdir/new.txt';
        $link = $this->directory . '/dangling.txt';
        self::assertTrue(mkdir(dirname($target)));
        self::assertTrue(symlink($target, $link));

        OutputFileWriter::write($link, 'new');

        self::assertTrue(is_link($link));
        self::assertSame('new', file_get_contents($target));
    }

    public function testDirectoryTargetFailsWithoutErasingDirectory(): void
    {
        $path = $this->directory . '/directory-target';
        self::assertTrue(mkdir($path));

        try {
            OutputFileWriter::write($path, 'contents');
            self::fail('Writing a directory should fail.');
        } catch (\RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        self::assertTrue(is_dir($path));
    }

    public function testTrailingSeparatorCannotClobberTheBasenameFile(): void
    {
        $path = $this->directory . '/output.txt';
        file_put_contents($path, 'old');

        try {
            OutputFileWriter::write($path . '/', 'new');
            self::fail('A local output path ending in a separator should fail.');
        } catch (\RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        self::assertSame('old', file_get_contents($path));
    }

    public function testPhpStdoutStreamRetainsLegacyExactOutput(): void
    {
        $script = $this->directory . '/stdout.php';
        file_put_contents($script, <<<'PHP'
<?php
require $argv[1];
\Bblslug\Console\OutputFileWriter::write('php://stdout', 'exact output');
PHP);
        $process = proc_open(
            [PHP_BINARY, $script, dirname(__DIR__, 3) . '/vendor/autoload.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3)
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process));

        self::assertSame('exact output', $output);
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
