<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliOperationTimeoutTest extends TestCase
{
    public function testValidOperationTimeoutIsAcceptedByDryRunWithoutHttp(): void
    {
        $result = $this->runCli(['--dry-run', '--operation-timeout=1'], 'Hello');

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('Hello', $result['stdout']);
    }

    public function testExpiredTranslationDoesNotEmitOrOverwriteOutput(): void
    {
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-operation-destination-');
        self::assertIsString($destination);
        file_put_contents($destination, 'sentinel');
        try {
            $r = $this->runCli(
                ['--operation-timeout=1', '--text-transport=segments', '--translated=' . $destination],
                'Hello'
            );
            self::assertNotSame(0, $r['exitCode']);
            self::assertSame('', $r['stdout']);
            self::assertStringContainsString('operation timeout', $r['stderr']);
            self::assertSame('sentinel', file_get_contents($destination));
        } finally {
            unlink($destination);
        }
    }

    public function testMalformedOperationTimeoutOptionsAreRejected(): void
    {
        foreach (
            [
                '--operation-timeout=0',
                '--operation-timeout=-1',
                '--operation-timeout=1.5',
                '--operation-timeout=1e2',
                '--operation-timeout=9223372036854775808',
                '--operation-timeout=',
                '--operation-timeout',
                '--operation-timeout=1 --operation-timeout=2',
            ] as $option
        ) {
            $options = str_contains($option, ' ') ? explode(' ', $option) : [$option];
            $result = $this->runCli($options, 'Hello');

            self::assertNotSame(0, $result['exitCode'], $option);
            self::assertSame('', $result['stdout'], $option);
        }
    }

    /** @param list<string> $options */
    private function runCli(array $options, string $input): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/Fixtures/console/operation_timeout.php',
            '--model=openai:gpt-5-mini',
            '--format=text',
            ...$options,
        ];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['OPENAI_API_KEY' => 'fixture-key']
        );
        self::assertIsResource($process);
        fwrite($pipes[0], $input);
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
