<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use PHPUnit\Framework\TestCase;

final class HtmlOpaqueMemoryTest extends TestCase
{
    /** @dataProvider profiles */
    public function testDenseProtectionPreservesSourceWithinMemoryBudget(string $profile, string $limit): void
    {
        $process = proc_open([
            PHP_BINARY, '-d', 'memory_limit=' . $limit, '-d', 'max_execution_time=30',
            dirname(__DIR__, 2) . '/Fixtures/translation/html_opaque_memory.php', $profile,
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $error);
        $result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        self::assertGreaterThan(1024 * 1024 - 64, $result['bytes']);
        self::assertGreaterThan(10000, $result['segments']);
    }

    /** @return list<array{string,string}> */
    public static function profiles(): array
    {
        return [['markers', '96M'], ['opaque', '64M']];
    }
}
