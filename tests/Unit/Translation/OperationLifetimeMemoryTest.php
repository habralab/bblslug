<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use PHPUnit\Framework\TestCase;

final class OperationLifetimeMemoryTest extends TestCase
{
    /** @dataProvider profiles */
    public function testTranslationPreservesPacketsWithinMemoryBudget(string $profile, string $limit): void
    {
        $command = [
            PHP_BINARY,
            '-d', 'memory_limit=' . $limit,
            '-d', 'max_execution_time=30',
            dirname(__DIR__, 2) . '/Fixtures/translation/operation_lifetime_memory.php',
            $profile,
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
        self::assertGreaterThan(100, $result['calls']);
        self::assertGreaterThanOrEqual($profile === 'text' ? 128 * 1024 - 3 : 1024 * 1024 - 12, $result['bytes']);
        self::assertGreaterThan(0, $result['peak']);
        self::assertSame($profile === 'native-html' ? $result['calls'] : 0, $result['nativeSchemaPackets']);
    }

    /** @return list<array{string,string}> */
    public static function profiles(): array
    {
        return [['native-html', '128M'], ['markers-html', '96M'], ['text', '64M']];
    }
}
