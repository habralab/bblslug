<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Translation;

use Bblslug\Translation\BatchCheckpoint;
use PHPUnit\Framework\TestCase;

final class BatchCheckpointTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bblslug-checkpoint-' . bin2hex(random_bytes(5));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testPersistsAndResumesContiguousPrefix(): void
    {
        $path = $this->directory . '/state.json';
        $record = ['text' => 'translated', 'httpStatus' => 200, 'consumed' => ['api' => ['total' => 3]]];
        $checkpoint = new BatchCheckpoint($path, 'fingerprint', 2);
        self::assertNull($checkpoint->get(0));
        $beforeInode = fileinode($path);
        $checkpoint->append(0, $record);
        clearstatcache(true, $path);
        self::assertSame($beforeInode, fileinode($path));
        self::assertSame($record, $checkpoint->get(0));
        unset($checkpoint);
        $resumed = new BatchCheckpoint($path, 'fingerprint', 2);
        self::assertSame($record, $resumed->get(0));
        self::assertNull($resumed->get(1));
    }

    public function testRejectsIdentityMismatchAndCorruption(): void
    {
        $path = $this->directory . '/state.json';
        $checkpoint = new BatchCheckpoint($path, 'fingerprint', 1);
        $checkpoint->append(0, ['text' => 'x', 'httpStatus' => 201, 'consumed' => []]);
        unset($checkpoint);
        $this->expectException(\RuntimeException::class);
        new BatchCheckpoint($path, 'other', 1);
    }

    public function testIdentityMismatchPreservesExistingBytes(): void
    {
        $path = $this->directory . '/state.json';
        $checkpoint = new BatchCheckpoint($path, 'fingerprint', 1);
        $checkpoint->append(0, ['text' => 'x', 'httpStatus' => 201, 'consumed' => []]);
        unset($checkpoint);
        $before = file_get_contents($path);
        try {
            new BatchCheckpoint($path, 'fingerprint', 2);
            self::fail('Expected packet-count mismatch');
        } catch (\RuntimeException $exception) {
            self::assertStringNotContainsString('x', $exception->getMessage());
        }
        self::assertSame($before, file_get_contents($path));
    }

    public function testRejectsChecksumCorruptionAndTruncation(): void
    {
        $path = $this->directory . '/state.json';
        $checkpoint = new BatchCheckpoint($path, 'fingerprint', 1);
        $checkpoint->append(0, ['text' => 'x', 'httpStatus' => 200, 'consumed' => []]);
        unset($checkpoint);
        $lines = file($path);
        $decoded = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
        $decoded['record']['text'] = 'changed';
        $lines[1] = json_encode($decoded, JSON_THROW_ON_ERROR) . "\n";
        file_put_contents($path, implode('', $lines));
        try {
            new BatchCheckpoint($path, 'fingerprint', 1);
            self::fail('Expected checksum rejection');
        } catch (\RuntimeException $exception) {
            self::assertStringNotContainsString('changed', $exception->getMessage());
        }
        file_put_contents($path, '{"version":');
        try {
            new BatchCheckpoint($path, 'fingerprint', 1);
            self::fail('Expected truncated-file rejection');
        } catch (\RuntimeException $exception) {
            self::assertStringNotContainsString('version', $exception->getMessage());
        }
    }

    public function testRejectsInvalidRecordAndUsageValues(): void
    {
        $checkpoint = new BatchCheckpoint($this->directory . '/state.json', 'fingerprint', 2);
        $invalid = [
            ['text' => 'x', 'httpStatus' => 199, 'consumed' => []],
            ['text' => 'x', 'httpStatus' => 300, 'consumed' => []],
            ['text' => 'x', 'httpStatus' => 200, 'consumed' => ['api' => ['total' => -1]]],
            ['text' => 'x', 'httpStatus' => 200, 'consumed' => ['api' => ['total' => '1']]],
            ['text' => 'x', 'httpStatus' => 200, 'consumed' => ['api' => ['breakdown' => ['input' => -1]]]],
            ['text' => 'x', 'httpStatus' => 200, 'consumed' => ['api' => ['unknown' => 1]]],
        ];
        foreach ($invalid as $record) {
            try {
                $checkpoint->append(0, $record);
                self::fail('Expected invalid record rejection');
            } catch (\InvalidArgumentException) {
                self::assertNull($checkpoint->get(0));
            }
        }
    }

    /** @dataProvider unencodableRecords */
    public function testEncodingFailureLeavesPrefixReadyForRetry(array $record): void
    {
        $path = $this->directory . '/state.json';
        $checkpoint = new BatchCheckpoint($path, 'fingerprint', 1);
        $before = file_get_contents($path);
        try {
            $checkpoint->append(0, $record);
            self::fail('Expected JSON encoding rejection');
        } catch (\JsonException) {
            self::assertNull($checkpoint->get(0));
        }
        self::assertSame($before, file_get_contents($path));
        self::assertSame([], glob($this->directory . '/.batch-checkpoint-*'));
        $valid = ['text' => 'retry', 'httpStatus' => 200, 'consumed' => []];
        $checkpoint->append(0, $valid);
        self::assertSame($valid, $checkpoint->get(0));
        unset($checkpoint);
        $resumed = new BatchCheckpoint($path, 'fingerprint', 1);
        self::assertSame($valid, $resumed->get(0));
    }

    /** @return list<array{array<string,mixed>}> */
    public static function unencodableRecords(): array
    {
        return [
            [['text' => "\xff", 'httpStatus' => 200, 'consumed' => []]],
            [['text' => 'text', 'httpStatus' => 200, 'consumed' => ["\xff" => ['total' => 1]]]],
            [['text' => 'text', 'httpStatus' => 200, 'consumed' => ['api' => ['breakdown' => ["\xff" => 1]]]]],
        ];
    }

    public function testRejectsBadIndexesAndUsage(): void
    {
        $checkpoint = new BatchCheckpoint($this->directory . '/state.json', 'fingerprint', 1);
        $this->expectException(\InvalidArgumentException::class);
        $checkpoint->append(1, ['text' => 'x', 'httpStatus' => 200, 'consumed' => []]);
    }

    public function testRejectsOutOfOrderAndOutOfBoundsIndexes(): void
    {
        $checkpoint = new BatchCheckpoint($this->directory . '/state.json', 'fingerprint', 1);
        foreach ([-1, 1] as $index) {
            try {
                $checkpoint->append($index, ['text' => 'x', 'httpStatus' => 200, 'consumed' => []]);
                self::fail('Expected index rejection');
            } catch (\InvalidArgumentException) {
                self::assertNull($checkpoint->get(0));
            }
        }
        $checkpoint->append(0, ['text' => 'x', 'httpStatus' => 200, 'consumed' => []]);
        try {
            $checkpoint->append(0, ['text' => 'again', 'httpStatus' => 200, 'consumed' => []]);
            self::fail('Expected out-of-order rejection');
        } catch (\InvalidArgumentException) {
            self::assertSame('x', $checkpoint->get(0)['text']);
        }
    }

    public function testLockIsPrivate(): void
    {
        $path = $this->directory . '/state.json';
        $checkpoint = new BatchCheckpoint($path, 'fingerprint', 0);
        self::assertSame(0600, fileperms($path . '.lock') & 0777);
        self::assertSame(0600, fileperms($path) & 0777);
        unset($checkpoint);
    }

    public function testLockContentionAndRelease(): void
    {
        $path = $this->directory . '/state.json';
        $checkpoint = new BatchCheckpoint($path, 'fingerprint', 0);
        $lock = fopen($path . '.lock', 'r+b');
        self::assertNotFalse($lock);
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB) === false);
        unset($checkpoint);
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        flock($lock, LOCK_UN);
        fclose($lock);
        $reopened = new BatchCheckpoint($path, 'fingerprint', 0);
        unset($reopened);
    }

    public function testRejectsSymlinkStateAndLock(): void
    {
        $state = $this->directory . '/state.json';
        file_put_contents($this->directory . '/target', 'target');
        symlink($this->directory . '/target', $state);
        try {
            new BatchCheckpoint($state, 'fingerprint', 0);
            self::fail('Expected symlink state rejection');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }
        unlink($state);
        unlink($state . '.lock');
        symlink($this->directory . '/target', $state . '.lock');
        try {
            new BatchCheckpoint($state, 'fingerprint', 0);
            self::fail('Expected symlink lock rejection');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }
    }

    public function testRejectsNonLocalAndMissingParentPaths(): void
    {
        foreach (['', "bad\0path", 'file://checkpoint.json'] as $path) {
            try {
                new BatchCheckpoint($path, 'fingerprint', 0);
                self::fail('Expected invalid path rejection');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
        try {
            new BatchCheckpoint($this->directory . '/missing/state.json', 'fingerprint', 0);
            self::fail('Expected missing parent rejection');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }
    }
}
