<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use PHPUnit\Framework\TestCase;

final class MarkerShieldingMemoryTest extends TestCase
{
    /** @dataProvider sourceKinds */
    public function testDenseMarkersRestoreWithinBoundedMemory(string $kind): void
    {
        $command = [
            PHP_BINARY,
            '-d', 'memory_limit=64M',
            '-d', 'max_execution_time=30',
            dirname(__DIR__, 2) . '/Fixtures/translation/marker_memory.php',
            $kind,
        ];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $error);
        $result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        self::assertGreaterThanOrEqual(1024 * 1024, $result['bytes']);
        self::assertSame(0, $result['captures']);
    }

    /** @return list<array{string}> */
    public static function sourceKinds(): array
    {
        return [['plain'], ['json']];
    }
}
