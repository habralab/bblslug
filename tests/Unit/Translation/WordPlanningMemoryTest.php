<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use PHPUnit\Framework\TestCase;

final class WordPlanningMemoryTest extends TestCase
{
    /** @dataProvider operations */
    public function testWordsAndLookaheadPreservePacketsWithinMemoryBudget(string $kind, string $outcome): void
    {
        $command = [
            PHP_BINARY,
            '-d', 'memory_limit=64M',
            '-d', 'max_execution_time=30',
            dirname(__DIR__, 2) . '/Fixtures/translation/word_planning_memory.php',
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
        self::assertSame($outcome, $result['outcome']);
        if ($kind === 'api') {
            self::assertGreaterThanOrEqual(100, $result['calls']);
        } else {
            self::assertSame(0, $result['calls']);
        }
    }

    /** @return list<array{string,string}> */
    public static function operations(): array
    {
        return [
            ['words', 'restored'],
            ['initial-chain', 'restored'],
            ['middle-chain', 'restored'],
            ['long-word', 'restored'],
            ['rejected-word', 'rejected'],
            ['api', 'restored'],
            ['api-rejected', 'rejected'],
        ];
    }
}
