<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Translation;

use Bblslug\Translation\BatchCheckpoint;
use PHPUnit\Framework\TestCase;

final class CheckpointPersistenceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/checkpoint-persistence-' . bin2hex(random_bytes(5));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/{*,.batch-checkpoint-*}', GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testVersionOneBytesAndResume(): void
    {
        $path = $this->directory . '/state.json';
        $fingerprint = "fingerprint/é\"\\\n";
        $inputs = [
            ['text' => "é / \"\\\n\t\0\u{2028}", 'httpStatus' => 201,
                'consumed' => ['z' => ['breakdown' => ['z' => 2, 'a' => 1], 'total' => 3], 'a' => []]],
            ['text' => '', 'httpStatus' => 200, 'consumed' => []],
            ['text' => str_repeat('大型/"\\', 20000), 'httpStatus' => 299,
                'consumed' => ['api' => ['breakdown' => [], 'total' => 0]]],
        ];
        $normalized = $inputs;
        $normalized[0]['consumed'] = ['a' => [], 'z' => ['total' => 3, 'breakdown' => ['a' => 1, 'z' => 2]]];
        $normalized[2]['consumed'] = ['api' => ['total' => 0, 'breakdown' => []]];
        file_put_contents($path, json_encode([
            'version' => 1, 'fingerprint' => $fingerprint, 'packetCount' => count($inputs),
            'contiguousPrefixRecords' => [], 'checksums' => [],
        ], self::jsonFlags()) . "\n");
        $checkpoint = new BatchCheckpoint($path, $fingerprint, count($inputs));
        $records = [];
        $checksums = [];
        $this->assertSnapshot($path, $fingerprint, count($inputs), $records, $checksums);
        foreach ($inputs as $index => $record) {
            $checkpoint->append($index, $record);
            $records[] = $normalized[$index];
            $checksums[] = hash('sha256', json_encode($normalized[$index], self::jsonFlags()));
            $this->assertSnapshot($path, $fingerprint, count($inputs), $records, $checksums);
            self::assertSame($normalized[$index], $checkpoint->get($index));
        }
        unset($checkpoint);
        $resumed = new BatchCheckpoint($path, $fingerprint, count($inputs));
        foreach ($normalized as $index => $record) {
            self::assertSame($record, $resumed->get($index));
        }
        self::assertNull($resumed->get(count($inputs)));
    }

    /** @dataProvider persistenceFailures */
    public function testFailurePreservesPrefixAndAllowsRetry(string $fault): void
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/translation/checkpoint_persistence.php',
            $this->directory . '/state.json', $fault];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Could not persist checkpoint', $result['error']);
        self::assertTrue($result['unchanged']);
        self::assertSame(['text' => 'saved', 'httpStatus' => 200, 'consumed' => []], $result['first']);
        self::assertNull($result['second']);
        self::assertSame([], $result['temps']);
        self::assertTrue($result['lockHeld']);
        self::assertTrue($result['retry']);
        self::assertTrue($result['resume']);
        if ($fault === 'after-write') {
            self::assertGreaterThanOrEqual(2, $result['writes']);
        }
    }

    /** @return list<array{string}> */
    public static function persistenceFailures(): array
    {
        return [['fwrite'], ['short-write'], ['after-write'], ['fflush'], ['fsync'], ['rename']];
    }

    private static function jsonFlags(): int
    {
        return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    }

    private function assertSnapshot(
        string $path,
        string $fingerprint,
        int $count,
        array $records,
        array $checksums
    ): void {
        $expected = json_encode([
            'version' => 1,
            'fingerprint' => $fingerprint,
            'packetCount' => $count,
            'contiguousPrefixRecords' => $records,
            'checksums' => $checksums,
        ], self::jsonFlags()) . "\n";
        self::assertSame($expected, file_get_contents($path));
    }
}
