<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliSegmentBatchBytesTest extends TestCase
{
    public function testByteCapSplitsTextSegmentsAndIsForwardedToRequests(): void
    {
        $counter = tempnam(sys_get_temp_dir(), 'bblslug-byte-counter-');
        self::assertIsString($counter);
        file_put_contents($counter, '0');
        try {
            $result = $this->runCli(
                ['--text-transport=segments', '--segment-batch-max-bytes=25', '--no-validate'],
                "Hello\nWorld\nThird",
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );
            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertSame('3', file_get_contents($counter));
            self::assertSame("Bonjour\nT:World\nT:Third", $result['stdout']);
        } finally {
            unlink($counter);
        }
    }

    public function testDryRunRejectsAnUnbreakableSegmentAndDoesNotWriteOutput(): void
    {
        $result = $this->runCli(
            ['--dry-run', '--text-transport=segments', '--segment-batch-max-bytes=10'],
            'unbreakableword'
        );

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
    }

    public function testLateFailurePreservesDestinationAndStopsBeforeThirdRequest(): void
    {
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-byte-destination-');
        $counter = tempnam(sys_get_temp_dir(), 'bblslug-byte-counter-');
        self::assertIsString($destination);
        self::assertIsString($counter);
        file_put_contents($destination, 'sentinel');
        file_put_contents($counter, '0');
        try {
            $r = $this->runCli(
                ['--text-transport=segments', '--segment-batch-max-bytes=25', '--no-validate',
                    '--translated=' . $destination],
                "Hello\nWorld\nThird",
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter, 'BBLSLUG_TEST_FAIL_BATCH' => '2']
            );
            self::assertNotSame(0, $r['exitCode']);
            self::assertSame('', $r['stdout']);
            self::assertSame('2', file_get_contents($counter));
            self::assertSame('sentinel', file_get_contents($destination));
        } finally {
            unlink($destination);
            unlink($counter);
        }
    }

    public function testSelectedJsonHtmlDryRunUsesBytePlanAndKeepsInputExact(): void
    {
        $input = '{"body":"<p>One two three four five six seven eight nine</p>","meta":1.0000}';
        $r = $this->runCli(
            ['--dry-run', '--html-transport=segments', '--html-json-pointer=/body', '--segment-batch-max-bytes=25'],
            $input,
            [],
            'json'
        );
        self::assertSame(0, $r['exitCode'], $r['stderr']);
        self::assertSame($input, $r['stdout']);
    }

    public function testRepeatableAbbreviationsAffectTheRealRequestPlanAndDryRun(): void
    {
        $source = 'Dr. Smith met Prof. Jones. Another reviewer agreed. ';
        $source = str_repeat($source, 4);
        $options = ['--text-transport=segments', '--segment-batch-max-bytes=100',
            '--segment-abbreviation=Dr.', '--segment-abbreviation', 'Prof.'];
        $r = $this->runCli($options, $source);
        self::assertSame(0, $r['exitCode'], $r['stderr']);
        self::assertSame(4, substr_count($r['stdout'], 'T:Dr. Smith met Prof. Jones.'));
        self::assertStringNotContainsString('T:Jones.', $r['stdout']);
        $r = $this->runCli([...$options, '--dry-run'], $source);
        self::assertSame(0, $r['exitCode'], $r['stderr']);
        self::assertSame($source, $r['stdout']);
    }

    public function testMalformedAbbreviationOptionsAreRejectedWithoutOutput(): void
    {
        $base = ['--text-transport=segments', '--segment-batch-max-bytes=100'];
        foreach (
            [['--segment-abbreviation='], ['--segment-abbreviation'],
            ['--segment-abbreviation', '--verbose'], ['--segment-abbreviation=Dr'],
            ['--segment-abbreviation=Dr. Smith'], ['--segment-abbreviation', '']] as $bad
        ) {
            $r = $this->runCli([...$base, ...$bad], 'Hello');
            self::assertNotSame(0, $r['exitCode']);
            self::assertSame('', $r['stdout']);
        }
        foreach ([['--text-transport=segments'], []] as $base) {
            $r = $this->runCli([...$base, '--segment-abbreviation=Dr.'], 'Hello');
            self::assertNotSame(0, $r['exitCode']);
            self::assertSame('', $r['stdout']);
        }
    }

    public function testInvalidByteCapOptionsAndModesAreRejectedUpfront(): void
    {
        foreach (['0', '-1', '1.5', '1e2', '9223372036854775808', ''] as $value) {
            $result = $this->runCli(
                ['--text-transport=segments', '--segment-batch-max-bytes=' . $value],
                'Hello'
            );
            self::assertNotSame(0, $result['exitCode'], $value);
            self::assertSame('', $result['stdout']);
        }
        foreach (
            [
                ['--segment-batch-max-bytes=20'],
                ['--text-transport=segments', '--segment-batch-max-bytes=20', '--segment-batch-max-bytes=21'],
                ['--text-transport=segments', '--segment-batch-max-bytes'],
            ] as $options
        ) {
            $result = $this->runCli($options, 'Hello');
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
        }
    }

    /** @param list<string> $options @param array<string,string> $extraEnv */
    private function runCli(
        array $options,
        ?string $input,
        array $extraEnv = [],
        string $format = 'text'
    ): array {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/Fixtures/console/segment_batches.php',
            '--model=openai:gpt-5-mini',
            '--format=' . $format,
            ...$options,
        ];
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

        return [
            'exitCode' => proc_close($process),
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        ];
    }
}
