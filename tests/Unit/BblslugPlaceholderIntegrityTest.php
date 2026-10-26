<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugPlaceholderIntegrityTest extends TestCase
{
    public function testReorderedMarkersRestoreTheCorrespondingUrls(): void
    {
        $input = 'First https://first.example/path then https://second.example/path';
        $result = $this->runFixture(['input' => $input, 'filters' => ['url'], 'mutation' => 'reorder']);

        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame(
            'First https://second.example/path then https://first.example/path',
            $result['result']
        );
    }

    /** @dataProvider markerLosses */
    public function testMarkerLossOrDuplicationFailsBeforeRestoration(string $mutation): void
    {
        $input = 'Keep https://private.example/secret and https://another.example/token.';
        $result = $this->runFixture([
            'input' => $input,
            'filters' => ['url'],
            'mutation' => $mutation,
        ]);

        $this->assertPlaceholderMismatch($result);
        self::assertStringNotContainsString('private.example', $result['message']);
        self::assertStringNotContainsString('another.example', $result['message']);
    }

    /** @return array<string,array{string}> */
    public static function markerLosses(): array
    {
        return ['drop' => ['drop'], 'duplicate' => ['duplicate']];
    }

    public function testChangedMarkerFailsEvenWhenContainerValidationIsDisabled(): void
    {
        $result = $this->runFixture([
            'input' => 'Visit https://private.example/secret.',
            'filters' => ['url'],
            'mutation' => 'substitute',
            'validate' => false,
        ]);

        $this->assertPlaceholderMismatch($result);
    }

    public function testUnknownMarkerFailsWhenPreparedInputHasNoMarkers(): void
    {
        $result = $this->runFixture([
            'input' => 'Plain text',
            'filters' => ['url'],
            'mutation' => 'unknown',
        ]);

        $this->assertPlaceholderMismatch($result);
    }

    /** @dataProvider markerLosses */
    public function testJsonMarkerLossOrDuplicationFailsWithValidJson(string $mutation): void
    {
        $result = $this->runFixture([
            'format' => 'json',
            'input' => '{"first":"https://first.example","second":"https://second.example"}',
            'filters' => ['url'],
            'mutation' => $mutation,
        ]);

        $this->assertPlaceholderMismatch($result);
    }

    public function testHtmlCodeMarkerDeletionFails(): void
    {
        $result = $this->runFixture([
            'format' => 'html',
            'input' => '<p>Text <code>https://private.example/secret</code></p>',
            'filters' => ['html_code'],
            'mutation' => 'drop',
        ]);

        $this->assertPlaceholderMismatch($result);
    }

    public function testLiteralMarkerAndUrlSurviveRoundTrip(): void
    {
        $input = 'Literal @@0@@ beside <code>@@1@@</code> and https://example.test/path.';
        foreach ([['url'], ['html_code'], ['url', 'html_code'], ['html_code', 'url']] as $filters) {
            $result = $this->runFixture([
                'input' => $input,
                'filters' => $filters,
            ]);

            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame($input, $result['result']);
        }
    }

    public function testNoFiltersKeepExistingUnrestrictedBehavior(): void
    {
        $result = $this->runFixture([
            'input' => 'https://source.example/secret',
            'filters' => [],
            'translated' => 'Translated text',
            'mutation' => 'identity',
        ]);

        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame('Translated text', $result['result']);
    }

    /** @param array<string,mixed> $scenario */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/placeholder_output.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            []
        );
        $this->assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result, $stdout);
        return $result;
    }

    /** @param array<string,mixed> $result */
    private function assertPlaceholderMismatch(array $result): void
    {
        self::assertSame('RuntimeException', $result['type']);
        self::assertStringContainsString('Placeholder mismatch', $result['message']);
    }
}
