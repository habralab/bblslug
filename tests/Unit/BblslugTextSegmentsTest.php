<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugTextSegmentsTest extends TestCase
{
    public function testProvidersTranslateTextSegmentsAndRestoreLineStructure(): void
    {
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash', 'deepl:free', 'yandex:gpt-lite'] as $model) {
            $result = $this->runFixture([
                'model' => $model,
                'input' => " Hello\r\nworld\nthird\r",
                'translated' => '{"s0":"Bonjour","s1":"monde","s2":"troisième"}',
                'textTransport' => 'segments',
                'variables' => str_starts_with($model, 'deepl:')
                    ? ['json_transport' => 'text_batch']
                    : (str_starts_with($model, 'yandex:') ? ['folder_id' => 'fixture-folder'] : []),
            ]);
            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame(" Bonjour\r\nmonde\ntroisième\r", $result['result']);
            self::assertSame(1, $result['networkCalls']);
        }
    }

    public function testMissingSegmentsAreRejectedEvenWhenValidationIsDisabled(): void
    {
        $input = implode("\n", array_map(static fn (int $i): string => "line $i", range(0, 98)));
        $incomplete = array_fill_keys(array_map(static fn (int $i): string => 's' . $i, range(0, 18)), 'translated');
        $result = $this->runFixture([
            'input' => $input,
            'translated' => json_encode($incomplete, JSON_THROW_ON_ERROR),
            'textTransport' => 'segments',
            'validate' => false,
        ]);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('text', strtolower((string) $result['message']));
        self::assertSame(1, $result['networkCalls']);
    }

    public function testNinetyNineSegmentsRestoreInProviderOrderIndependentOfJsonKeyOrder(): void
    {
        $input = implode("\n", array_map(static fn (int $i): string => "line $i", range(0, 98)));
        $translations = [];
        foreach (range(98, 0) as $i) {
            $translations['s' . $i] = 'translated ' . $i;
        }
        $result = $this->runFixture([
            'input' => $input,
            'translated' => json_encode($translations, JSON_THROW_ON_ERROR),
            'textTransport' => 'segments',
            'validate' => false,
        ]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame(
            implode("\n", array_map(static fn (int $i): string => 'translated ' . $i, range(0, 98))),
            $result['result']
        );
    }

    public function testNoTextAndDryRunDoNotMakeNetworkCalls(): void
    {
        foreach ([['input' => " \t\r\n@@0@@"], ['input' => "Hello\nWorld", 'dryRun' => true]] as $scenario) {
            $result = $this->runFixture($scenario + ['textTransport' => 'segments']);
            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame($scenario['input'], $result['result']);
            self::assertSame(0, $result['networkCalls']);
        }
    }

    public function testFiltersProtectUrlsAndCodeWhileTranslatingRemainingText(): void
    {
        $result = $this->runFixture([
            'input' => "Hello https://example.org/path\n<code>fixed code</code>\nWorld",
            'filters' => ['url', 'html_code'],
            'translated' => '{"s0":"Bonjour","s1":"Monde"}',
            'textTransport' => 'segments',
        ]);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertStringContainsString('https://example.org/path', $result['result']);
        self::assertStringContainsString('<code>fixed code</code>', $result['result']);
        self::assertStringContainsString('Bonjour', $result['result']);
        self::assertStringContainsString('Monde', $result['result']);
        self::assertStringNotContainsString('example.org/path', (string) $result['request']);
        self::assertStringNotContainsString('fixed code', (string) $result['request']);
    }

    public function testDefaultDocumentModeAndIncompatibleOptionsRemainRejected(): void
    {
        $result = $this->runFixture(['input' => "Hello\nWorld", 'textTransport' => 'document']);
        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame('{"s0":"Bonjour"}', $result['result']);
        self::assertStringContainsString("Hello\\nWorld", (string) $result['request']);
        foreach (
            [
                ['format' => 'html', 'textTransport' => 'segments'],
                ['textTransport' => 'invalid'],
                ['textTransport' => 'segments', 'variables' => ['response_schema' => ['type' => 'object']]],
            ] as $scenario
        ) {
            $failure = $this->runFixture($scenario);
            self::assertSame(\InvalidArgumentException::class, $failure['type']);
            self::assertSame(0, $failure['networkCalls']);
        }
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/text_segments.php'],
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
