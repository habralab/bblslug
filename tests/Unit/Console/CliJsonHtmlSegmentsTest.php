<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliJsonHtmlSegmentsTest extends TestCase
{
    public function testOnlySelectedJsonHtmlStringsAreTranslated(): void
    {
        $result = $this->runCli([
            '--html-transport=segments', '--html-json-pointer=/body', '--no-validate',
        ], '{"body":"<p>Hello</p>","other":"<em>World</em>"}');

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame(
            '{"body":"<p>Bonjour</p>","other":"<em>World</em>"}',
            trim($result['stdout'])
        );
    }

    public function testRepeatedSelectorsAndNestedArrayPointerWithEscapedQuotes(): void
    {
        $result = $this->runCli(
            ['--html-transport=segments', '--html-json-pointer=/a', '--html-json-pointer=/b'],
            '{"a":"<p>Hello \"friend\"</p>","b":"<em>World</em>",'
                . '"items":["<i>Ignored</i>","<strong>Nested</strong>"]}',
            ['BBLSLUG_TEST_SEGMENT_COUNT' => '2']
        );

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertStringContainsString('Bonjour', $result['stdout']);
        self::assertStringContainsString('Monde', $result['stdout']);
        self::assertStringContainsString('<i>Ignored</i>', $result['stdout']);

        $nested = $this->runCli(
            ['--html-transport=segments', '--html-json-pointer=/items/1'],
            '{"items":["<i>Ignored</i>","<strong>Nested</strong>"]}'
        );
        self::assertSame(0, $nested['exitCode'], $nested['stderr']);
        self::assertStringContainsString('Bonjour', $nested['stdout']);
        self::assertStringContainsString('<i>Ignored</i>', $nested['stdout']);
    }

    public function testDryRunValidatesSelectedPointerAndFiltersOnlySelectedField(): void
    {
        $invalid = $this->runCli(
            ['--dry-run', '--html-transport=segments', '--html-json-pointer=/body'],
            '{"body":"<p title=\"oops>Hello","code":"@@not-a-filter@@"}'
        );
        self::assertNotSame(0, $invalid['exitCode']);
        self::assertSame('', $invalid['stdout']);

        $valid = $this->runCli(
            ['--dry-run', '--html-transport=segments', '--html-json-pointer=/body', '--filters=url'],
            '{"body":"<p>https://example.test</p>","code":"@@literal@@"}'
        );
        self::assertSame(0, $valid['exitCode'], $valid['stderr']);
        self::assertStringContainsString('@@literal@@', $valid['stdout']);
        self::assertStringContainsString('body', $valid['stdout']);
    }

    public function testMissingSegmentIdPreservesSourceAndDestination(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'bblslug-json-segments-source-');
        $destination = tempnam(sys_get_temp_dir(), 'bblslug-json-segments-destination-');
        self::assertIsString($source);
        self::assertIsString($destination);
        $input = '{"body":"<p>Hello</p>"}';
        file_put_contents($source, $input);
        file_put_contents($destination, 'sentinel');
        try {
            $result = $this->runCli(
                [
                    '--html-transport=segments', '--html-json-pointer=/body',
                    '--source=' . $source, '--translated=' . $destination,
                ],
                null,
                ['BBLSLUG_TEST_MISSING_SEGMENT' => '1']
            );
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
            self::assertSame($input, file_get_contents($source));
            self::assertSame('sentinel', file_get_contents($destination));
        } finally {
            unlink($source);
            unlink($destination);
        }
    }

    public function testSegmentTransportRequiresPointersAndDocumentTransportRejectsThem(): void
    {
        foreach (
            [
                ['--html-transport=segments'],
                ['--html-json-pointer=/body'],
            ] as $options
        ) {
            $result = $this->runCli($options, '{"body":"<p>Hello</p>"}');
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
        }
    }

    public function testMissingAndEmptyPointersAreRejected(): void
    {
        foreach (
            [
                ['--html-transport=segments', '--html-json-pointer'],
                ['--html-transport=segments', '--html-json-pointer='],
            ] as $options
        ) {
            $result = $this->runCli($options, '{"body":"<p>Hello</p>"}');
            self::assertNotSame(0, $result['exitCode']);
            self::assertSame('', $result['stdout']);
        }

        $root = $this->runCli(
            ['--html-transport=segments', '--html-json-pointer', '', '--no-validate'],
            '"<p>Hello</p>"'
        );
        self::assertSame(0, $root['exitCode'], $root['stderr']);
        self::assertSame('"<p>Bonjour</p>"', trim($root['stdout']));
    }

    /** @param array<int,string> $options @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(array $options, ?string $input, array $extraEnv = []): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/console/json_html_segments.php',
                '--model=openai:gpt-5-mini', '--format=json', ...$options],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            array_merge(['OPENAI_API_KEY' => 'fixture-key'], $extraEnv)
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
        return ['exitCode' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
