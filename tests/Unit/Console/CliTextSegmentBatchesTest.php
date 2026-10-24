<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliTextSegmentBatchesTest extends TestCase
{
    public function testBatchSizeSplitsTextSegmentsAndPreservesLineSeparators(): void
    {
        $counter = tempnam(sys_get_temp_dir(), 'bblslug-text-batch-counter-');
        self::assertIsString($counter);
        file_put_contents($counter, '0');
        try {
            $result = $this->runCli(
                ['--text-transport=segments', '--text-segment-batch-size=2', '--no-validate'],
                "Hello\r\nWorld\nThird\r\nFourth\nFifth",
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );
            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertSame('3', file_get_contents($counter));
            self::assertSame("Bonjour\r\nT:World\nT:Third\r\nT:Fourth\nT:Fifth", $result['stdout']);
        } finally {
            unlink($counter);
        }
    }

    public function testSecondBatchFailureStopsRequestsAndPreservesDestination(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'bblslug-text-batch-source-');
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-text-batch-destination-');
        $counter = tempnam(sys_get_temp_dir(), 'bblslug-text-batch-counter-');
        self::assertIsString($source);
        self::assertIsString($destination);
        self::assertIsString($counter);
        $input = "Hello\nWorld\nThird\nFourth\nFifth";
        file_put_contents($source, $input);
        file_put_contents($destination, 'sentinel');
        file_put_contents($counter, '0');
        try {
            $result = $this->runCli(
                ['--text-transport=segments', '--text-segment-batch-size=2', '--no-validate',
                    '--source=' . $source, '--translated=' . $destination],
                null,
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter, 'BBLSLUG_TEST_FAIL_BATCH' => '2']
            );
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
            self::assertSame('2', file_get_contents($counter));
            self::assertSame($input, file_get_contents($source));
            self::assertSame('sentinel', file_get_contents($destination));
        } finally {
            unlink($source);
            unlink($destination);
            unlink($counter);
        }
    }

    public function testInvalidTextBatchOptionsAreRejectedUpfront(): void
    {
        foreach (['0', '-1', '1.5', '1e2', '9223372036854775808', ''] as $value) {
            $result = $this->runCli(
                ['--text-transport=segments', '--text-segment-batch-size=' . $value],
                'Hello'
            );
            self::assertNotSame(0, $result['exitCode'], $value);
            self::assertSame('', $result['stdout']);
        }
        foreach (
            [
                ['--text-transport=segments', '--text-segment-batch-size=2', '--text-segment-batch-size=3'],
                ['--text-transport=segments', '--text-segment-batch-size'],
                ['--text-segment-batch-size=2'],
                ['--text-transport=document', '--text-segment-batch-size=2'],
                ['--text-transport=segments', '--text-segment-batch-size=2', '--html-segment-batch-size=2'],
            ] as $options
        ) {
            $result = $this->runCli($options, 'Hello');
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
        }
    }

    public function testDryRunPreservesPlaintextAndDoesNotCallHttp(): void
    {
        $counter = tempnam(sys_get_temp_dir(), 'bblslug-text-batch-counter-');
        self::assertIsString($counter);
        try {
            $input = "Hello\r\nworld";
            $result = $this->runCli(
                ['--dry-run', '--text-transport=segments', '--text-segment-batch-size=2'],
                $input,
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );
            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertSame('', (string) @file_get_contents($counter));
            self::assertSame($input, $result['stdout']);
        } finally {
            unlink($counter);
        }
    }

    /** @param list<string> $options @param array<string,string> $extraEnv */
    private function runCli(array $options, ?string $input, array $extraEnv = []): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/console/segment_batches.php',
            '--model=openai:gpt-5-mini', '--format=text', ...$options];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['OPENAI_API_KEY' => 'fixture-key'] + $extraEnv
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
        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
