<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliReservedVariablesTest extends TestCase
{
    public function testDryRunRejectsReservedVariableBeforeReadingInput(): void
    {
        $result = $this->runCli(null, '--dry-run', '--variables=format=text');

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString('Reserved option in variables: format', $result['stderr']);
        self::assertStringNotContainsString('Fatal error', $result['stderr']);
    }

    public function testNormalModeRejectsReservedValueBeforeInputAndDoesNotPrintIt(): void
    {
        $result = $this->runCli(null, '--variables=context=sensitive-value', '--source=/missing-reserved-test-input');
        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString('Reserved option in variables: context', $result['stderr']);
        self::assertStringNotContainsString('sensitive-value', $result['stderr']);
        self::assertStringNotContainsString('Fatal error', $result['stderr']);
    }

    public function testDryRunAllowsProviderSpecificVariable(): void
    {
        $result = $this->runCli('Hello', '--dry-run', '--variables=max_completion_tokens=123');

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('Hello', $result['stdout']);
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
