<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugVerboseDiagnosticsTest extends TestCase
{
    private const KEY = 'demo +/key=value';

    public function testVerbosePlaceholderMapIsMaskedAndResultKeepsProtectedBytes(): void
    {
        $result = $this->runFixture(['format' => 'html', 'verbose' => true]);
        self::assertSame('success', $result['type']);
        self::assertStringContainsString('https://example.org/?token=demo%20%2B%2Fkey%3Dvalue', $result['result']);
        self::assertStringContainsString('echo "demo +/key=value";', $result['result']);
        $feedback = implode("\n", array_column($result['feedback'], 1));
        self::assertStringContainsString('Verbose: placeholders captured', $feedback);
        self::assertStringContainsString('- url:', $feedback);
        self::assertStringContainsString('- html_code:', $feedback);
        self::assertStringNotContainsString(self::KEY, $feedback);
        self::assertStringNotContainsString(rawurlencode(self::KEY), $feedback);
        self::assertStringNotContainsString(urlencode(self::KEY), $feedback);
    }

    public function testVerboseFalseOmitsPlaceholderMap(): void
    {
        $result = $this->runFixture(['format' => 'html', 'verbose' => false]);
        self::assertSame('success', $result['type']);
        self::assertStringNotContainsString(
            'Verbose: placeholders captured',
            implode("\n", array_column($result['feedback'], 1))
        );
    }

    public function testPlaceholderPreviewMasksSecretThatStartsAfter190Characters(): void
    {
        // The key begins at character 196 of the captured code block and crosses the preview limit.
        $source = '<pre><code>' . str_repeat('A', 190) . self::KEY . '</code></pre>';
        $result = $this->runFixture([
            'format' => 'html', 'source' => $source, 'filters' => ['html_code'], 'verbose' => true,
        ]);
        self::assertSame('success', $result['type']);
        self::assertSame($source, $result['result']);
        $feedback = implode("\n", array_column($result['feedback'], 1));
        self::assertSame(1, preg_match('/^  @@[0-9]+@@ => (.+)$/m', $feedback, $matches));
        $preview = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('***', $preview);
        self::assertStringNotContainsString('demo', $preview);
        self::assertStringContainsString('…', $preview);
        self::assertLessThanOrEqual(201, mb_strlen($preview));
    }

    public function testPlaceholderMapCapsEntriesAt50(): void
    {
        $links = '';
        for ($i = 0; $i < 60; $i++) {
            $links .= '<a href="https://example.org/item' . $i . '">item</a>';
        }
        $result = $this->runFixture([
            'format' => 'html', 'source' => '<div>' . $links . '</div>', 'filters' => ['url'], 'verbose' => true,
        ]);
        self::assertSame('success', $result['type']);
        $feedback = implode("\n", array_column($result['feedback'], 1));
        self::assertStringContainsString('- url: 60 item(s)', $feedback);
        self::assertStringContainsString('… (+10 more)', $feedback);
        self::assertSame(50, preg_match_all('/^  @@[0-9]+@@ => /m', $feedback));
    }

    /** @dataProvider invalidScenarioProvider */
    public function testVerboseValidationErrorsContainBoundedMaskedDump(
        string $format,
        string $scenario,
        string $heading
    ): void {
        $result = $this->runFixture(['format' => $format, 'scenario' => $scenario, 'verbose' => true]);
        self::assertSame('RuntimeException', $result['type']);
        self::assertStringContainsString($heading, $result['message']);
        self::assertStringNotContainsString(self::KEY, $result['message']);
        $position = strpos($result['message'], $heading);
        self::assertNotFalse($position);
        $dump = substr($result['message'], $position + strlen($heading));
        self::assertLessThanOrEqual(2003, mb_strlen($dump));
        self::assertGreaterThanOrEqual(5, substr_count($dump, '***'));
        foreach ([rawurlencode(self::KEY), urlencode(self::KEY), 'demo +\\/key=value'] as $encoded) {
            self::assertStringNotContainsString($encoded, $dump);
        }
        self::assertStringContainsString('…', $dump);
    }

    /** @return array<string,array{string,string,string}> */
    public static function invalidScenarioProvider(): array
    {
        return [
            'json syntax' => ['json', 'json-invalid', '[Restored JSON (invalid) dump]'],
            'html syntax' => ['html', 'html-invalid', '[Restored HTML (invalid) dump]'],
            'html structure' => ['html', 'html-structure', '[Restored HTML (invalid) dump]'],
        ];
    }

    public function testFeedbackCallbackFailureDoesNotBreakTranslation(): void
    {
        $result = $this->runFixture(['format' => 'html', 'verbose' => true, 'throwFeedback' => true]);
        self::assertSame('success', $result['type']);
    }

    /** @dataProvider invalidScenarioProvider */
    public function testNonVerboseValidationErrorsExcludeRestoredDump(
        string $format,
        string $scenario,
        string $heading
    ): void {
        $result = $this->runFixture(['format' => $format, 'scenario' => $scenario, 'verbose' => false]);
        self::assertSame('RuntimeException', $result['type']);
        self::assertStringNotContainsString($heading, $result['message']);
    }

    /**
     * @param array<string,mixed> $scenario
     * @return array{type:string,result?:string,message?:string,feedback:array<int,array{string,string}>}
     */
    private function runFixture(array $scenario): array
    {
        $environment = ($scenario['throwFeedback'] ?? false) ? ['BBLSLUG_DIAGNOSTIC_THROW_FEEDBACK' => '1'] : [];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/verbose_diagnostics.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            $environment
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        /** @var array{type:string,result?:string,message?:string,feedback:array<int,array{string,string}>} $decoded */
        $decoded = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }
}
