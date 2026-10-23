<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugJsonHtmlTest extends TestCase
{
    public function testJsonFormatsRejectReturnedHtmlMutationIncludingNativeStructuredOutput(): void
    {
        foreach (['json', 'linedJson', 'structured_json'] as $format) {
            $result = $this->runFixture([
                'format' => $format,
                'input' => '{"message":"<p>Hello</p>"}',
                'translated' => '{"message":"<div>Bonjour</div>"}',
                'validateHtmlJsonPointers' => ['/message'],
            ]);
            self::assertSame(\RuntimeException::class, $result['type']);
            self::assertSame(1, $result['httpCalls']);
            self::assertStringContainsString('HTML inside JSON mismatch', $result['message']);
            self::assertStringContainsString('/message', $result['message']);
        }
    }

    public function testRestoredProtectedFragmentsAndTranslatedHtmlAreValidatedTogether(): void
    {
        $result = $this->runFixture([
            'input' => '{"body":"<p>Hello <code>fixed</code></p>"}',
            'translated' => '{"body":"<p>Bonjour @@0@@</p>"}',
            'filters' => ['html_code'],
            'validateHtmlJsonPointers' => ['/body'],
        ]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame('{"body":"<p>Bonjour <code>fixed</code></p>"}', $result['result']);
    }

    public function testDefaultBehaviorAndExplicitValidationOptOutRemainAvailable(): void
    {
        foreach ([[], ['validate' => false, 'validateHtmlJsonPointers' => ['/body']]] as $options) {
            $result = $this->runFixture(array_merge([
                'input' => '{"body":"<p>Hello</p>"}',
                'translated' => '{"body":"<div>Bonjour</div>"}',
            ], $options));
            self::assertSame('success', $result['type']);
        }
    }

    public function testInvalidSourceSelectionFailsBeforeHttpIncludingDryRun(): void
    {
        foreach ([false, true] as $dryRun) {
            foreach (['/missing', '/count', 'body', '/bad~2'] as $pointer) {
                $result = $this->runFixture([
                    'input' => '{"body":"<p>Hello</p>","count":3}',
                    'translated' => '{}',
                    'dryRun' => $dryRun,
                    'validateHtmlJsonPointers' => [$pointer],
                ]);
                self::assertSame(\InvalidArgumentException::class, $result['type']);
                self::assertSame(0, $result['httpCalls']);
            }
        }
        $invalidHtml = $this->runFixture([
            'input' => '{"body":"<p>Bad</div>"}',
            'validateHtmlJsonPointers' => ['/body'],
        ]);
        self::assertSame(\InvalidArgumentException::class, $invalidHtml['type']);
        self::assertSame(0, $invalidHtml['httpCalls']);

        $result = $this->runFixture([
            'format' => 'html', 'input' => '<p>Hello</p>', 'validateHtmlJsonPointers' => ['/body'],
        ]);
        self::assertSame(\InvalidArgumentException::class, $result['type']);
        self::assertSame(0, $result['httpCalls']);
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $fixture = ($scenario['format'] ?? '') === 'structured_json' ? 'structured_output.php' : 'json_output.php';
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/' . $fixture],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            []
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        return json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
