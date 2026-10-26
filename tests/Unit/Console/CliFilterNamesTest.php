<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliFilterNamesTest extends TestCase
{
    /** @dataProvider invalidFilters */
    public function testInvalidFiltersAreReportedBeforeReadingNormalInput(string $filters, string $message): void
    {
        $result = $this->runCli(null, '--filters=' . $filters, '--source=/missing-filter-test-input');

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString($message, $result['stderr']);
        self::assertStringNotContainsString('Cannot read source file', $result['stderr']);
        self::assertStringNotContainsString('Fatal error', $result['stderr']);
    }

    /** @dataProvider invalidFilters */
    public function testInvalidFiltersAreReportedCleanlyInDryRun(string $filters, string $message): void
    {
        $result = $this->runCli('Hello', '--dry-run', '--filters=' . $filters);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString($message, $result['stderr']);
        self::assertStringNotContainsString('Fatal error', $result['stderr']);
    }

    public function testExplicitlyEmptyFiltersMeansNoFilters(): void
    {
        $result = $this->runCli('Hello', '--dry-run', '--filters=');

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('Hello', $result['stdout']);
        self::assertStringNotContainsString('Fatal error', $result['stderr']);
    }

    /** @return array<string,array{string,string}> */
    public static function invalidFilters(): array
    {
        return [
            'unknown filter' => ['urll', 'Unknown filter: urll'],
            'empty middle token' => ['url,,html_code', 'Unknown filter: '],
            'trailing comma' => ['url,', 'Unknown filter: '],
            'malformed html filter' => ['html_pre bad', 'Invalid HTML tag name for filter'],
        ];
    }

    /** @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(?string $input, string ...$arguments): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 3) . '/bin/bblslug',
            '--model=openai:gpt-5-mini',
            '--format=text',
            ...$arguments,
        ];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            ['BBLSLUG_OPENAI_API_KEY' => 'fake-test-key']
        );
        self::assertIsResource($process);
        if ($input !== null) {
            fwrite($pipes[0], $input);
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        ];
    }
}
