<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use PHPUnit\Framework\TestCase;

final class CheckpointMemoryTest extends TestCase
{
    /** @dataProvider profiles */
    public function testCheckpointPreservesRecordsAndBytesWithinMemoryBudget(string $profile, string $format): void
    {
        $directory = sys_get_temp_dir() . '/bblslug-checkpoint-memory-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory, 0700));
        try {
            $limit = $format === 'legacy-resume' ? '48M' : '32M';
            $command = [
                PHP_BINARY, '-d', 'memory_limit=' . $limit, '-d', 'max_execution_time=30',
                dirname(__DIR__, 2) . '/Fixtures/translation/checkpoint_memory.php',
                $profile, $directory . '/state.json', $format,
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
            self::assertSame(32, $result['records']);
            self::assertGreaterThanOrEqual(16 * 1024 * 1024, $result['fileBytes']);
            if ($format === 'journal') {
                self::assertSame($result['fileBytes'], $result['writtenBytes']);
                self::assertSame(33, $result['writes']);
            }
        } finally {
            foreach (scandir($directory) ?: [] as $file) {
                if ($file !== '.' && $file !== '..') {
                    unlink($directory . '/' . $file);
                }
            }
            rmdir($directory);
        }
    }

    /** @return list<array{string,string}> */
    public static function profiles(): array
    {
        return [
            ['plain', 'legacy'], ['escaped', 'legacy'], ['plain', 'journal'], ['escaped', 'journal'],
            ['plain', 'legacy-resume'],
        ];
    }
}
