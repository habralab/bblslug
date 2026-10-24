<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliTextSegmentsTest extends TestCase
{
    public function testSegmentsTransportIsTextOnly(): void
    {
        $result = $this->runCli(['--text-transport=segments'], '<p>Hello</p>', 'html');

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString('requires --format=text', $result['stderr']);
    }

    public function testMalformedMissingEmptyAndRepeatedFlagsAreRejectedBeforeInputProcessing(): void
    {
        foreach (
            [
                ['--text-transport'],
                ['--text-transport='],
                ['--text-transport=segments', '--text-transport=document'],
                ['--text-transport', '--model=openai:gpt-5-mini'],
            ] as $options
        ) {
            $result = $this->runCli($options, 'Hello');
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
        }
    }

    public function testDryRunSegmentsPreservesPreparedPlainText(): void
    {
        $input = "Hello\nworld";
        $result = $this->runCli(['--dry-run', '--text-transport=segments'], $input);

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame($input, $result['stdout']);
    }

    public function testNoValidateStillValidatesTextEncodingInSegmentsMode(): void
    {
        $invalid = "ok\xFF";
        $result = $this->runCli(['--dry-run', '--no-validate', '--text-transport=segments'], $invalid);

        self::assertNotSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
    }

    public function testSegmentsTransportReconstructsExactLineSeparators(): void
    {
        $result = $this->runCli(
            ['--text-transport=segments'],
            " Hello\r\nworld",
            'text',
            dirname(__DIR__, 2) . '/Fixtures/console/html_segments.php'
        );

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame(" Bonjour\r\nMonde", $result['stdout']);
    }

    public function testMissingSegmentDoesNotWriteOrEmitOutputWithValidationDisabled(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'bblslug-text-source-');
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-text-destination-');
        self::assertIsString($source);
        self::assertIsString($destination);
        file_put_contents($source, "Hello\nWorld\nThird");
        file_put_contents($destination, 'sentinel');
        try {
            $result = $this->runCli(
                ['--text-transport=segments', '--no-validate', '--source=' . $source,
                    '--translated=' . $destination],
                '',
                'text',
                dirname(__DIR__, 2) . '/Fixtures/console/html_segments.php'
            );
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
            self::assertStringContainsString('missing [s2]', $result['stderr']);
            self::assertSame('sentinel', file_get_contents($destination));
        } finally {
            unlink($source);
            unlink($destination);
        }
    }

    public function testSegmentsRejectsResponseSchemaFromFileAndVariables(): void
    {
        foreach (
            [
                ['--text-transport=segments', '--dry-run', '--response-schema=/does/not/exist'],
                ['--text-transport=segments', '--dry-run', '--variables=response_schema=enabled'],
            ] as $options
        ) {
            $result = $this->runCli($options, 'Hello');
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
            self::assertStringContainsString('incompatible', strtolower($result['stderr']));
        }
    }

    public function testExplicitDocumentTransportKeepsDryRunOutputUnchanged(): void
    {
        $input = "Hello\r\nworld";
        $result = $this->runCli(['--dry-run', '--text-transport=document'], $input);

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame($input, $result['stdout']);
    }

    /** @param list<string> $options @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(
        array $options,
        string $input,
        string $format = 'text',
        ?string $fixture = null,
        array $extraEnv = []
    ): array {
        $command = [PHP_BINARY, $fixture ?? dirname(__DIR__, 3) . '/bblslug.php',
            '--model=openai:gpt-5-mini', '--format=' . $format, ...$options];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['OPENAI_API_KEY' => 'fixture-key'] + $extraEnv
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
