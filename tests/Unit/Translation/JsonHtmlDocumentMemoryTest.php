<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use PHPUnit\Framework\TestCase;

final class JsonHtmlDocumentMemoryTest extends TestCase
{
    /** @dataProvider operations */
    public function testLargeJsonPreservesSelectionAndValidationWithinMemoryBudget(
        string $kind,
        string $limit,
        string $outcome
    ): void {
        $command = [
            PHP_BINARY,
            '-d', 'memory_limit=' . $limit,
            '-d', 'max_execution_time=30',
            dirname(__DIR__, 2) . '/Fixtures/translation/json_html_cursor_memory.php',
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
        self::assertSame($kind === 'api' ? 1 : 0, $result['calls']);
    }

    /** @return list<array{string,string,string}> */
    public static function operations(): array
    {
        return [
            ['array', '64M', 'restored'],
            ['object', '64M', 'restored'],
            ['duplicate', '64M', 'rejected'],
            ['wrong-type', '64M', 'rejected'],
            ['long-token', '64M', 'scanned'],
            ['api', '128M', 'translated'],
        ];
    }
}
