<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliEmbeddedJsonTest extends TestCase
{
    public function testFlagRejectsDamagedEmbeddedJsonAndDefaultBehaviorIsPreserved(): void
    {
        $default = $this->runCli([]);
        self::assertSame(0, $default['exitCode'], $default['stderr']);
        self::assertSame(['payload' => '{"count":2}'], json_decode(trim($default['stdout']), true));

        $checked = $this->runCli(['--validate-embedded-json']);
        self::assertNotSame(0, $checked['exitCode']);
        self::assertSame('', $checked['stdout']);
        self::assertStringContainsString('Embedded JSON mismatch', $checked['stderr']);

        $disabled = $this->runCli(['--validate-embedded-json', '--no-validate']);
        self::assertSame(0, $disabled['exitCode'], $disabled['stderr']);
        self::assertSame($default['stdout'], $disabled['stdout']);
    }

    /** @param array<int,string> $options @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(array $options): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/console/embedded_json.php',
                '--model=deepl:free', '--format=json', ...$options],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['DEEPL_FREE_API_KEY' => 'fixture-key']
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode(['payload' => '{"count":1}'], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
