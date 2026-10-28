<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use PHPUnit\Framework\TestCase;

final class RestorationMemoryTest extends TestCase
{
    /** @dataProvider profiles */
    public function testDenseTextCompletesRestorationWithinMemoryBudget(string $profile): void
    {
        $process = proc_open([
            PHP_BINARY, '-d', 'memory_limit=256M', '-d', 'max_execution_time=60',
            dirname(__DIR__, 2) . '/Fixtures/translation/restoration_memory.php', $profile,
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $error);
        $result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1048575, $result['bytes']);
        self::assertSame(5462, $result['calls']);
    }

    /** @return list<array{string}> */
    public static function profiles(): array
    {
        return [['identity'], ['translated-reversed']];
    }
}
