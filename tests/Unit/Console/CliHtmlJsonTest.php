<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliHtmlJsonTest extends TestCase
{
    public function testSelectedPointerRejectsMutatedHtmlWithoutWritingOutput(): void
    {
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-html-json-');
        self::assertIsString($destination);
        $source = tempnam(sys_get_temp_dir(), 'bblslug-html-source-');
        self::assertIsString($source);
        $input = '{"body":"<p>Hello</p>","other":"<em>World</em>"}';
        file_put_contents($source, $input);
        file_put_contents($destination, 'sentinel');
        try {
            $result = $this->runCli([
                '--validate-html-json-pointer=/other', '--source=' . $source, '--translated=' . $destination,
            ]);
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
            self::assertStringContainsString('HTML inside JSON mismatch', $result['stderr']);
            self::assertSame('sentinel', file_get_contents($destination));
            self::assertSame($input, file_get_contents($source));
        } finally {
            unlink($destination);
            unlink($source);
        }
    }

    public function testMultiplePointersAndNoValidateAreSupported(): void
    {
        $checked = $this->runCli([
            '--validate-html-json-pointer=/body',
            '--validate-html-json-pointer=/other',
        ]);
        self::assertNotSame(0, $checked['exitCode']);
        self::assertSame('', $checked['stdout']);

        $disabled = $this->runCli([
            '--validate-html-json-pointer=/body',
            '--no-validate',
        ]);
        self::assertSame(0, $disabled['exitCode'], $disabled['stderr']);
        $expected = json_encode(
            ['body' => '<p>Hello</p>', 'other' => '<strong>World</strong>'],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        self::assertSame(
            $expected,
            trim($disabled['stdout'])
        );
    }

    public function testDefaultBehaviorRemainsUnchangedAndDryRunRejectsInvalidSourcePointer(): void
    {
        $default = $this->runCli([]);
        self::assertSame(0, $default['exitCode'], $default['stderr']);

        $dryRun = $this->runCli(['--dry-run', '--validate-html-json-pointer=/missing']);
        self::assertNotSame(0, $dryRun['exitCode']);
        self::assertSame('', $dryRun['stdout']);
        self::assertStringContainsString('JSON Pointer', $dryRun['stderr']);

        $rootInput = json_encode('<p>Root</p>', JSON_THROW_ON_ERROR);
        $root = $this->runCli(['--dry-run', '--validate-html-json-pointer', ''], $rootInput);
        self::assertSame(0, $root['exitCode'], $root['stderr']);
        self::assertSame($rootInput, trim($root['stdout']));

        $rootObject = $this->runCli(['--dry-run', '--validate-html-json-pointer', '']);
        self::assertNotSame(0, $rootObject['exitCode']);
        self::assertStringContainsString('does not resolve to a string', $rootObject['stderr']);

        foreach (
            [
            ['--validate-html-json-pointer'],
            ['--validate-html-json-pointer=/body', '--validate-html-json-pointer'],
            ] as $missingArgument
        ) {
            $missing = $this->runCli($missingArgument);
            self::assertNotSame(0, $missing['exitCode']);
            self::assertSame('', $missing['stdout']);
        }

        $equalsEmpty = $this->runCli(['--dry-run', '--validate-html-json-pointer=']);
        self::assertNotSame(0, $equalsEmpty['exitCode']);
        self::assertStringContainsString('separate empty argument', $equalsEmpty['stderr']);
    }

    /** @param array<int,string> $options @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(array $options, ?string $input = null): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/console/html_json.php',
                '--model=openai:gpt-5-mini', '--format=json', ...$options],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['OPENAI_API_KEY' => 'fixture-key']
        );
        self::assertIsResource($process);
        $input ??= json_encode(
            ['body' => '<p>Hello</p>', 'other' => '<em>World</em>'],
            JSON_THROW_ON_ERROR
        );
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
