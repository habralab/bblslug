<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliHtmlSegmentsTest extends TestCase
{
    public function testSegmentsTransportTranslatesTextAndPreservesMarkup(): void
    {
        $result = $this->runCli(['--html-transport=segments'], '<p>Hello</p><img alt="World">');

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('<p>Bonjour</p><img alt="Monde">', trim($result['stdout']));
    }

    public function testMissingSegmentIdRejectsWithoutWritingOutput(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'bblslug-segments-source-');
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-segments-destination-');
        self::assertIsString($source);
        self::assertIsString($destination);
        file_put_contents($source, '<p>Hello</p>');
        file_put_contents($destination, 'sentinel');
        try {
            $result = $this->runCli([
                '--html-transport=segments', '--source=' . $source, '--translated=' . $destination,
            ], null);
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
            self::assertStringContainsString('segment ids', $result['stderr']);
            self::assertSame('<p>Hello</p>', file_get_contents($source));
            self::assertSame('sentinel', file_get_contents($destination));
        } finally {
            unlink($source);
            unlink($destination);
        }
    }

    public function testTransportValidationAppliesToFormatAndDryRun(): void
    {
        foreach (
            [
                ['--html-transport=bogus', '--format=html'],
                ['--html-transport=segments', '--format=text'],
                ['--html-transport=', '--format=html'],
            ] as $options
        ) {
            $result = $this->runCli($options, '<p>Hello</p>');
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
        }

        $dryRun = $this->runCli(['--dry-run', '--html-transport=segments'], '<p title="oops>Hello');
        self::assertNotSame(0, $dryRun['exitCode']);
        self::assertSame('', $dryRun['stdout']);
    }

    /** @param array<int,string> $options @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(array $options, ?string $input): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/console/html_segments.php',
            '--model=openai:gpt-5-mini', '--format=html', ...$options];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, [
            'OPENAI_API_KEY' => 'fixture-key',
        ]);
        self::assertIsResource($process);
        if ($input !== null) {
            fwrite($pipes[0], $input);
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
