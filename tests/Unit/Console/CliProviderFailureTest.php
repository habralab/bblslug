<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliProviderFailureTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/bblslug-cli-provider-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->temporaryDirectory);
    }

    /** @return array<string,array{case:string,model:string,reason:string}> */
    public static function failures(): array
    {
        return [
            'authentication' => ['http401', 'openai:gpt-5-mini', 'HTTP 401 error'],
            'rate limit' => ['http429', 'openai:gpt-5-mini', 'HTTP 429 error'],
            'anthropic credit' => ['anthropic-credit', 'anthropic:claude-haiku-5.5', 'Credit balance too low'],
            'malformed response' => ['malformed', 'openai:gpt-5-mini', 'OpenAI translation failed'],
            'timeout' => ['timeout', 'openai:gpt-5-mini', 'timed out'],
        ];
    }

    /** @dataProvider failures */
    public function testProviderFailureDoesNotOverwriteFilesOrLeakSecrets(
        string $case,
        string $model,
        string $reason
    ): void {
        $source = $this->temporaryDirectory . '/source.txt';
        $destination = $this->temporaryDirectory . '/translated.txt';
        $sourceContents = 'source marker https://source.example/keep';
        $destinationContents = 'existing destination marker';
        file_put_contents($source, $sourceContents);
        file_put_contents($destination, $destinationContents);
        $key = 'cli-secret +/key=value';

        $result = $this->runCli($case, $model, $key, $source, $destination);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString($reason, $result['stderr']);
        self::assertStringNotContainsString('Translation complete', $result['stderr']);
        foreach ([$key, rawurlencode($key), urlencode($key), json_encode($key, JSON_THROW_ON_ERROR)] as $secret) {
            self::assertStringNotContainsString((string) $secret, $result['stderr']);
        }
        self::assertSame($sourceContents, file_get_contents($source));
        self::assertSame($destinationContents, file_get_contents($destination));
        self::assertFileDoesNotExist($source . '.prepared');
    }

    /** @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(string $case, string $model, string $key, string $source, string $destination): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/Fixtures/console/provider_failure.php',
            '--model=' . $model,
            '--format=text',
            '--source=' . $source,
            '--translated=' . $destination,
            '--verbose',
        ];
        $environment = [
            'BBLSLUG_TEST_CASE' => $case,
            'BBLSLUG_OPENAI_API_KEY' => $key,
            'BBLSLUG_ANTHROPIC_API_KEY' => $key,
        ];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            $environment
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
