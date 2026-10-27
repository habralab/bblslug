<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliCheckpointTest extends TestCase
{
    public function testFailedRunResumesFromCheckpointAndDoesNotRewriteDestination(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-source-');
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-destination-');
        $checkpoint = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-state-');
        $counter = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-counter-');
        self::assertIsString($source);
        self::assertIsString($destination);
        self::assertIsString($checkpoint);
        self::assertIsString($counter);
        $input = "Hello\nWorld\nThird";
        file_put_contents($source, $input);
        file_put_contents($destination, 'sentinel');
        file_put_contents($counter, '0');
        // Leave a missing path so the first run creates a new checkpoint.
        unlink($checkpoint);

        try {
            $options = [
                '--text-transport=segments', '--text-segment-batch-size=1', '--no-validate',
                '--source=' . $source, '--translated=' . $destination, '--checkpoint=' . $checkpoint,
            ];
            $failed = $this->runCli(
                $options,
                null,
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter, 'BBLSLUG_TEST_FAIL_BATCH' => '2']
            );
            self::assertNotSame(0, $failed['exitCode'], $failed['stderr']);
            self::assertSame('2', file_get_contents($counter));
            self::assertSame('sentinel', file_get_contents($destination));
            self::assertFileExists($checkpoint);
            $checkpointAfterFailure = file_get_contents($checkpoint);
            self::assertIsString($checkpointAfterFailure);

            // The fixture counter is process-independent, so reset it before the retry.
            file_put_contents($counter, '0');
            $resumed = $this->runCli($options, null, ['BBLSLUG_TEST_COUNTER_FILE' => $counter]);
            self::assertSame(0, $resumed['exitCode'], $resumed['stderr']);
            self::assertSame('2', file_get_contents($counter));
            self::assertSame("Bonjour\nT:World\nT:Third", file_get_contents($destination));

            // A fully completed checkpoint must make a subsequent invocation HTTP-free.
            $checkpointAfterSuccess = file_get_contents($checkpoint);
            self::assertIsString($checkpointAfterSuccess);
            file_put_contents($counter, '0');
            $cached = $this->runCli($options, null, ['BBLSLUG_TEST_COUNTER_FILE' => $counter]);
            self::assertSame(0, $cached['exitCode'], $cached['stderr']);
            self::assertSame('0', file_get_contents($counter));
            self::assertSame($checkpointAfterSuccess, file_get_contents($checkpoint));
            self::assertNotSame($checkpointAfterFailure, $checkpointAfterSuccess);
        } finally {
            @unlink($source);
            @unlink($destination);
            @unlink($checkpoint);
            @unlink($checkpoint . '.lock');
            @unlink($counter);
        }
    }

    public function testChangedInputIsRejectedBeforeHttpAndLeavesCheckpointUnchanged(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-source-');
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-destination-');
        $checkpoint = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-state-');
        $counter = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-counter-');
        self::assertIsString($source);
        self::assertIsString($destination);
        self::assertIsString($checkpoint);
        self::assertIsString($counter);
        file_put_contents($source, "Hello\nWorld\nThird");
        file_put_contents($destination, 'sentinel');
        file_put_contents($counter, '0');
        unlink($checkpoint);
        $options = [
            '--text-transport=segments', '--text-segment-batch-size=1', '--no-validate',
            '--source=' . $source, '--translated=' . $destination, '--checkpoint=' . $checkpoint,
        ];

        try {
            $first = $this->runCli(
                $options,
                null,
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter, 'BBLSLUG_TEST_FAIL_BATCH' => '2']
            );
            self::assertNotSame(0, $first['exitCode']);
            self::assertFileExists($checkpoint);
            $before = file_get_contents($checkpoint);
            self::assertIsString($before);
            file_put_contents($source, "Hello\nChanged\nThird");
            file_put_contents($counter, '0');
            $changed = $this->runCli($options, null, ['BBLSLUG_TEST_COUNTER_FILE' => $counter]);
            self::assertNotSame(0, $changed['exitCode']);
            self::assertSame('0', file_get_contents($counter));
            self::assertSame($before, file_get_contents($checkpoint));
            self::assertSame('sentinel', file_get_contents($destination));
        } finally {
            @unlink($source);
            @unlink($destination);
            @unlink($checkpoint);
            @unlink($checkpoint . '.lock');
            @unlink($counter);
        }
    }

    public function testDryRunDoesNotTouchCheckpointPathOrLock(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bblslug-checkpoint-dry-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory));
        $checkpoint = $directory . DIRECTORY_SEPARATOR . 'translation.checkpoint';
        try {
            $before = scandir($directory);
            self::assertIsArray($before);
            $result = $this->runCli([
                '--dry-run', '--text-transport=segments', '--text-segment-batch-size=1',
                '--checkpoint=' . $checkpoint,
            ], "Hello\nWorld");
            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertFalse(file_exists($checkpoint));
            self::assertSame($before, scandir($directory));
        } finally {
            @rmdir($directory);
        }
    }

    public function testCheckpointFlagAndModeRequirementsAreRejected(): void
    {
        $invalid = [
            ['--checkpoint'],
            ['--checkpoint='],
            ['--checkpoint=/tmp/a', '--checkpoint=/tmp/b'],
            ['--checkpoint=/tmp/a'],
            ['--text-transport=segments', '--checkpoint=/tmp/a'],
        ];
        foreach ($invalid as $options) {
            $result = $this->runCli($options, 'Hello');
            self::assertNotSame(0, $result['exitCode'], implode(' ', $options));
            self::assertSame('', $result['stdout']);
        }
    }

    public function testCheckpointCannotAliasInputPreparedOrOutputPath(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-source-');
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-destination-');
        self::assertIsString($source);
        self::assertIsString($destination);
        file_put_contents($source, 'Hello');
        file_put_contents($destination, 'sentinel');
        try {
            foreach ([$source, $destination, $source . '.prepared'] as $checkpoint) {
                $result = $this->runCli([
                    '--text-transport=segments', '--text-segment-batch-size=1', '--no-validate',
                    '--source=' . $source, '--translated=' . $destination,
                    '--checkpoint=' . $checkpoint,
                ], null);
                self::assertNotSame(0, $result['exitCode'], $checkpoint);
                self::assertSame('', $result['stdout']);
                self::assertSame('sentinel', file_get_contents($destination));
            }
        } finally {
            @unlink($source);
            @unlink($destination);
            @unlink($source . '.prepared');
        }
    }

    public function testDryRunCannotOverwriteCheckpointThroughPreparedOutput(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'bblslug-checkpoint-source-');
        self::assertIsString($source);
        file_put_contents($source, 'Hello');
        file_put_contents($source . '.prepared', 'saved-state');
        try {
            $r = $this->runCli([
                '--dry-run', '--text-transport=segments', '--text-segment-batch-size=1',
                '--source=' . $source, '--checkpoint=' . $source . '.prepared',
            ], null);
            self::assertNotSame(0, $r['exitCode']);
            self::assertSame('saved-state', file_get_contents($source . '.prepared'));
        } finally {
            unlink($source);
            unlink($source . '.prepared');
        }
    }

    public function testLockAndHardlinkAliasesCannotBeUsedAsOutput(): void
    {
        $directory = sys_get_temp_dir() . '/bblslug-alias-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory));
        $checkpoint = $directory . '/state.json';
        $hardlink = $directory . '/output.json';
        file_put_contents($checkpoint, 'sentinel');
        self::assertTrue(link($checkpoint, $hardlink));
        try {
            foreach ([$checkpoint . '.lock', $hardlink] as $output) {
                $r = $this->runCli([
                    '--text-transport=segments', '--text-segment-batch-size=1',
                    '--checkpoint=' . $checkpoint, '--translated=' . $output,
                ], 'Hello');
                self::assertNotSame(0, $r['exitCode']);
                self::assertSame('', $r['stdout']);
                self::assertStringContainsString('must not overwrite', $r['stderr']);
                self::assertSame('sentinel', file_get_contents($checkpoint));
            }
        } finally {
            unlink($hardlink);
            unlink($checkpoint);
            rmdir($directory);
        }
    }

    public function testChangingEvenUnusedAbbreviationsRejectsCheckpointBeforeHttp(): void
    {
        $checkpoint = tempnam(sys_get_temp_dir(), 'bblslug-abbreviation-state-');
        $counter = tempnam(sys_get_temp_dir(), 'bblslug-abbreviation-count-');
        self::assertIsString($checkpoint);
        self::assertIsString($counter);
        unlink($checkpoint);
        $input = "Hello\nWorld\nThird";
        $base = ['--text-transport=segments', '--text-segment-batch-size=1',
            '--segment-batch-max-bytes=100', '--checkpoint=' . $checkpoint];
        try {
            file_put_contents($counter, '0');
            $r = $this->runCli([...$base, '--segment-abbreviation=Dr.'], $input, [
                'BBLSLUG_TEST_COUNTER_FILE' => $counter, 'BBLSLUG_TEST_FAIL_BATCH' => '2',
            ]);
            self::assertNotSame(0, $r['exitCode']);
            $saved = file_get_contents($checkpoint);
            self::assertIsString($saved);
            file_put_contents($counter, '0');
            $r = $this->runCli(
                [...$base, '--segment-abbreviation=Prof.'],
                $input,
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );
            self::assertNotSame(0, $r['exitCode']);
            self::assertStringContainsString('identity mismatch', $r['stderr']);
            self::assertSame('0', file_get_contents($counter));
            self::assertSame($saved, file_get_contents($checkpoint));
            $r = $this->runCli(
                [...$base, '--segment-abbreviation=Dr.'],
                $input,
                ['BBLSLUG_TEST_COUNTER_FILE' => $counter]
            );
            self::assertSame(0, $r['exitCode'], $r['stderr']);
            self::assertSame('2', file_get_contents($counter));
        } finally {
            @unlink($checkpoint);
            @unlink($checkpoint . '.lock');
            @unlink($counter);
        }
    }

    /** @param list<string> $options @param array<string,string> $extraEnv */
    private function runCli(array $options, ?string $input, array $extraEnv = []): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/Fixtures/console/segment_batches.php',
            '--model=openai:gpt-5-mini', '--format=text', ...$options,
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
