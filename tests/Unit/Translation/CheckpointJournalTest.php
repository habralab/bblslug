<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Translation;

use Bblslug\Translation\BatchCheckpoint;
use PHPUnit\Framework\TestCase;

final class CheckpointJournalTest extends TestCase
{
    private string $directory;
    private string $path;
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    private const RECORD = ['text' => "é\"\\\n", 'httpStatus' => 200, 'consumed' => []];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/checkpoint-journal-' . bin2hex(random_bytes(5));
        mkdir($this->directory, 0700);
        $this->path = $this->directory . '/state';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    private function header(): string
    {
        return json_encode(['version' => 2, 'fingerprint' => 'fingerprint', 'packetCount' => 2], self::FLAGS) . "\n";
    }

    private function row(int $index, array $record = self::RECORD): string
    {
        return json_encode([
            'index' => $index, 'record' => $record,
            'checksum' => hash('sha256', json_encode($record, self::FLAGS)),
        ], self::FLAGS) . "\n";
    }

    public function testNewFileAppendsWithoutRewritingPrefixAndResumes(): void
    {
        $checkpoint = new BatchCheckpoint($this->path, 'fingerprint', 2);
        self::assertSame($this->header(), file_get_contents($this->path));
        $checkpoint->append(0, self::RECORD);
        self::assertSame($this->header() . $this->row(0), file_get_contents($this->path));
        $checkpoint->append(1, self::RECORD);
        self::assertSame($this->header() . $this->row(0) . $this->row(1), file_get_contents($this->path));
        unset($checkpoint);
        $resumed = new BatchCheckpoint($this->path, 'fingerprint', 2);
        self::assertSame(self::RECORD, $resumed->get(0));
        self::assertSame(self::RECORD, $resumed->get(1));
    }

    public function testEveryIncompleteFinalRowIsDiscardedAndCanBeReplaced(): void
    {
        $prefix = $this->header() . $this->row(0);
        $row = $this->row(1);
        for ($length = 1; $length < strlen($row); ++$length) {
            file_put_contents($this->path, $prefix . substr($row, 0, $length));
            $checkpoint = new BatchCheckpoint($this->path, 'fingerprint', 2);
            self::assertSame(self::RECORD, $checkpoint->get(0), "tail length $length");
            self::assertNull($checkpoint->get(1), "tail length $length");
            self::assertSame($prefix, file_get_contents($this->path), "tail length $length");
            $checkpoint->append(1, self::RECORD);
            self::assertSame($prefix . $row, file_get_contents($this->path), "tail length $length");
            unset($checkpoint);
        }
    }

    /** @dataProvider invalidJournals */
    public function testCompleteCorruptionIsRejectedWithoutMutation(string $bytes): void
    {
        file_put_contents($this->path, $bytes);
        try {
            new BatchCheckpoint($this->path, 'fingerprint', 2);
            self::fail('Expected invalid journal rejection');
        } catch (\RuntimeException) {
            self::assertSame($bytes, file_get_contents($this->path));
        }
    }

    public static function invalidJournals(): array
    {
        $header = json_encode(['version' => 2, 'fingerprint' => 'fingerprint', 'packetCount' => 2]) . "\n";
        $record = ['text' => 'x', 'httpStatus' => 200, 'consumed' => []];
        $checksum = hash('sha256', json_encode($record, self::FLAGS));
        $row = static fn (array $data): string => json_encode($data, self::FLAGS) . "\n";
        return [
            'unterminated header' => [rtrim($header, "\n")],
            'header extra key' => [json_encode(['version' => 2, 'fingerprint' => 'fingerprint',
                'packetCount' => 2, 'extra' => true]) . "\n"],
            'wrong version' => [str_replace('"version":2', '"version":3', $header)],
            'bad json complete' => [$header . "{bad}\n"],
            'wrong checksum' => [$header . $row(['index' => 0, 'record' => $record, 'checksum' => 'bad'])],
            'out of order' => [$header . $row(['index' => 1, 'record' => $record, 'checksum' => $checksum])],
            'extra row key' => [$header . $row(['index' => 0, 'record' => $record,
                'checksum' => $checksum, 'extra' => true])],
            'corrupt interior with tail' => [$header . "{bad}\n" . '{partial'],
            'duplicate index' => [$header . str_repeat($row(['index' => 0, 'record' => $record,
                'checksum' => $checksum]), 2)],
        ];
    }

    public function testIdentityMismatchDoesNotTruncateTail(): void
    {
        $bytes = $this->header() . $this->row(0) . '{partial';
        file_put_contents($this->path, $bytes);
        try {
            new BatchCheckpoint($this->path, 'other', 2);
            self::fail('Expected identity mismatch');
        } catch (\RuntimeException) {
            self::assertSame($bytes, file_get_contents($this->path));
        }
    }

    /** @dataProvider ioFailures */
    public function testFailedAppendRequiresReopen(string $fault, bool $complete): void
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/translation/checkpoint_journal_failure.php',
            $this->path, $fault], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($result['failed']);
        self::assertTrue($result['retryRejected']);
        self::assertTrue($result['prefixIntact']);
        self::assertSame($complete, $result['recoveredSecond']);
        self::assertTrue($result['resumeComplete']);
    }

    public static function ioFailures(): array
    {
        return [['fwrite', false], ['partial', false], ['fflush', true], ['fsync', true]];
    }
}
