<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliUnknownOptionsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bblslug-unknown-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }

    /** @return array<string,array{list<string>,string}> */
    public static function unknownOptions(): array
    {
        return [
            'standalone' => [['--dry-runn'], '--dry-runn'],
            'normal translation' => [['--model=openai:gpt-5-mini', '--format=text', '--dry-runn'], '--dry-runn'],
            'dry run' => [['--dry-run', '--dry-runn'], '--dry-runn'],
            'help' => [['--help', '--dry-runn'], '--dry-runn'],
            'model listing' => [['--list-models', '--dry-runn'], '--dry-runn'],
            'all model listing' => [['--list-models=all', '--dry-runn'], '--dry-runn'],
            'prompt listing' => [['--list-prompts', '--dry-runn'], '--dry-runn'],
            'checkpoint typo' => [['--checkpointt=path'], '--checkpointt'],
            'inline secret' => [['--bogus=inline-secret'], '--bogus'],
            'abbreviation' => [['--dry-r'], '--dry-r'],
            'short option' => [['-x'], '-x'],
        ];
    }

    /**
     * @dataProvider unknownOptions
     * @param list<string> $options
     */
    public function testUnknownOptionsFailBeforeAnySideEffects(array $options, string $unknown): void
    {
        $source = $this->directory . '/source';
        $prepared = $source . '.prepared';
        $output = $this->directory . '/output';
        $checkpoint = $this->directory . '/checkpoint';
        foreach ([$source, $prepared, $output, $checkpoint] as $path) {
            file_put_contents($path, 'sentinel');
        }
        $paths = ['--source=' . $source, '--translated=' . $output];
        if ($unknown === '--checkpointt') {
            $paths[] = '--checkpoint=' . $checkpoint;
        }
        $result = $this->runCli([...$options, ...$paths]);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertSame("\033[31mError:\033[0m Unknown option: {$unknown}.\n", $result['stderr']);
        self::assertStringNotContainsString('fixture-secret', $result['stderr']);
        self::assertFileDoesNotExist($this->directory . '/http');
        foreach ([$source, $prepared, $output, $checkpoint] as $path) {
            self::assertSame('sentinel', file_get_contents($path));
        }
        self::assertFileDoesNotExist($checkpoint . '.lock');
    }

    public function testRequiredValueThatLooksLikeAnOptionIsAccepted(): void
    {
        $result = $this->runCli([
            '--model=openai:gpt-5-mini', '--format=text', '--dry-run', '--context', '--not-an-option',
        ], 'Hello');

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertFileDoesNotExist($this->directory . '/http');
    }

    /** @param list<string> $options @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(array $options, string $input = ''): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/console/unknown_options.php', ...$options],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            ['BBLSLUG_OPENAI_API_KEY' => 'fixture-secret', 'BBLSLUG_TEST_HTTP_LOG' => $this->directory . '/http']
        );
        self::assertIsResource($process);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
