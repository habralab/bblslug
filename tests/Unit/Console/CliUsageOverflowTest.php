<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliUsageOverflowTest extends TestCase
{
    public function testOverflowOmitsTotalButKeepsBreakdownAndTranslation(): void
    {
        $result = $this->runCli((string) PHP_INT_MAX);
        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('<p>T:Hello</p><p>T:World</p>', $result['stdout']);
        self::assertMatchesRegularExpression('/Prompt:\s+8\r?\n/', $result['stderr']);
        self::assertMatchesRegularExpression('/Completion:\s+12\r?\n/', $result['stderr']);
        self::assertStringNotContainsString('Total:', $result['stderr']);
    }

    public function testNonOverflowStillPrintsTotal(): void
    {
        foreach (['10' => 20, '0' => 0] as $reported => $expected) {
            $result = $this->runCli((string) $reported);
            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertMatchesRegularExpression('/Total:\s+' . $expected . '\r?\n/', $result['stderr']);
        }
    }

    /** @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(string $total): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/console/usage_overflow.php',
                '--model=openai:gpt-5-mini', '--format=html', '--html-transport=segments',
                '--html-segment-batch-size=1', '--no-validate'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['OPENAI_API_KEY' => 'fixture-key', 'BBLSLUG_TEST_USAGE_TOTAL' => $total]
        );
        self::assertIsResource($process);
        fwrite($pipes[0], '<p>Hello</p><p>World</p>');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
