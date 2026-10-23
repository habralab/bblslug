<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliUnclosedFilterTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/bblslug-cli-unclosed-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->temporaryDirectory);
    }

    /** @dataProvider failureProvider */
    public function testUnclosedFilterFailsWithoutOutputOrFileMutation(array $case): void
    {
        $source = $this->temporaryDirectory . '/source.txt';
        $destination = $this->temporaryDirectory . '/translated.txt';
        $sourceContents = (string) $case['source'];
        $destinationContents = 'existing destination marker';
        file_put_contents($source, $sourceContents);
        file_put_contents($destination, $destinationContents);

        $result = $this->runCli($case, $source, $destination);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString('div', $result['stderr']);
        self::assertStringNotContainsString('synthetic-secret', $result['stderr']);
        self::assertSame($sourceContents, file_get_contents($source));
        self::assertSame($destinationContents, file_get_contents($destination));
        self::assertFileDoesNotExist($source . '.prepared');
    }

    /** @return array<string,array{array<string,string>}> */
    public static function failureProvider(): array
    {
        return [
            'html' => [['format' => 'html', 'source' => '<div>synthetic-secret']],
            'html no validate' => [[
                'format' => 'html', 'source' => '<div>synthetic-secret', 'extra' => '--no-validate',
            ]],
            'html dry run' => [[
                'format' => 'html', 'source' => '<div>synthetic-secret', 'extra' => '--dry-run',
            ]],
            'encoded html json' => [[
                'format' => 'json', 'source' => '{"body":"<div>synthetic-secret"}',
                'extra' => '--validate-embedded-json',
            ]],
            'encoded html json no validate' => [[
                'format' => 'json', 'source' => '{"body":"<div>synthetic-secret"}', 'extra' => '--no-validate',
            ]],
        ];
    }

    /** @param array<string,string> $case @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(array $case, string $source, string $destination): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/Fixtures/translation/unclosed_filter.php',
            '--model=openai:gpt-5-mini',
            '--format=' . $case['format'],
            '--filters=html_div',
            '--source=' . $source,
            '--translated=' . $destination,
            $case['extra'] ?? '',
        ];
        $command = array_values(array_filter($command, static fn (string $arg): bool => $arg !== ''));
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            ['BBLSLUG_UNCLOSED_FILTER_CLI' => '1', 'OPENAI_API_KEY' => 'fixture-key']
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
