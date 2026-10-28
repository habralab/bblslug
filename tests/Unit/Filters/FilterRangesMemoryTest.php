<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use PHPUnit\Framework\TestCase;

final class FilterRangesMemoryTest extends TestCase
{
    /** @dataProvider profiles */
    public function testFilterPreservesRawCapturesWithinMemoryBudget(string $profile, string $limit): void
    {
        $process = proc_open([
            PHP_BINARY, '-d', 'memory_limit=' . $limit, '-d', 'max_execution_time=30',
            dirname(__DIR__, 2) . '/Fixtures/translation/filter_ranges_memory.php', $profile,
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $error);
        $result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        self::assertGreaterThanOrEqual(1024 * 1024 - 32, $result['bytes']);
        self::assertGreaterThan(10000, $result['count']);
        if (str_starts_with($profile, 'api-')) {
            self::assertGreaterThan(0, $result['calls']);
        } else {
            self::assertSame(0, $result['calls']);
        }
    }

    /** @return list<array{string,string}> */
    public static function profiles(): array
    {
        return [
            ['url', '64M'], ['html', '128M'], ['nested-url', '96M'], ['nested-html', '128M'],
            ['api-url', '96M'], ['api-html', '128M'],
        ];
    }
}
