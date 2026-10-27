<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugSegmentSchemaTest extends TestCase
{
    /** @dataProvider nativeCases */
    public function testPacketSchemaRequiresEverySourceId(string $model, string $format, ?int $batchSize): void
    {
        $html = '<h1>Section 1</h1><h2>Section 1</h2><p>Last</p>';
        $input = $format === 'json' ? '{"untouched":1.2300,"body":' . json_encode($html) . '}'
            : ($format === 'text' ? "Section 1\nSection 1\nLast" : $html);
        $r = $this->runFixture([
            'model' => $model, 'format' => $format, 'input' => $input, 'batchSize' => $batchSize,
            'htmlJsonPointers' => $format === 'json' ? ['/body'] : [],
            'variables' => ['folder_id' => 'fixture-folder'],
        ]);
        self::assertSame('success', $r['type'], $r['message'] ?? '');
        self::assertSame($batchSize === null ? 1 : 2, $r['networkCalls']);
        foreach ($r['bodies'] as $index => $body) {
            $schema = $body['jsonSchema']['schema'] ?? $body['text']['format']['schema'];
            self::assertSame(array_keys($r['packets'][$index]), $schema['required']);
            self::assertSame($schema['required'], array_keys($schema['properties']));
            self::assertFalse($schema['additionalProperties']);
            foreach ($schema['properties'] as $property) {
                self::assertSame(['type' => 'string', 'minLength' => 1], $property);
            }
        }
        self::assertStringContainsString('T:Section 1', $r['result']['result']);
        self::assertStringContainsString('T:Last', $r['result']['result']);
        if ($format === 'json') {
            self::assertStringStartsWith('{"untouched":1.2300,"body":', $r['result']['result']);
        }
    }

    public static function nativeCases(): array
    {
        $cases = [];
        foreach (['yandex:gpt-lite', 'yandex:gpt-pro', 'xai:grok-4.7'] as $model) {
            foreach (['text', 'html', 'json'] as $format) {
                foreach ([null, 2] as $size) {
                    $cases[] = [$model, $format, $size];
                }
            }
        }
        return $cases;
    }

    public function testGeneratedSplitIdsAreRequiredByTheActualPacketSchema(): void
    {
        $r = $this->runFixture([
            'model' => 'xai:grok-4.7', 'format' => 'text', 'input' => str_repeat('Some words ', 30),
            'maxBytes' => 80, 'batchSize' => 8,
        ]);
        self::assertSame('success', $r['type'], $r['message'] ?? '');
        self::assertGreaterThan(1, $r['networkCalls']);
        foreach ($r['bodies'] as $index => $body) {
            self::assertSame(array_keys($r['packets'][$index]), $body['text']['format']['schema']['required']);
        }
    }

    public function testExplicitMarkersCustomPromptsAndUndeclaredModelsKeepTheirProtocol(): void
    {
        $cases = [
            ['model' => 'xai:grok-4.7', 'variables' => ['json_transport' => 'markers']],
            ['model' => 'yandex:gpt-lite', 'variables' => [
                'folder_id' => 'fixture-folder', 'json_transport' => 'markers',
            ]],
            ['model' => 'xai:grok-4.7', 'promptKey' => 'experimental_xml'],
            ['model' => 'xai:grok-4.6'],
        ];
        foreach ($cases as $case) {
            $r = $this->runFixture($case);
            self::assertSame('success', $r['type'], $r['message'] ?? '');
            foreach ($r['bodies'] as $body) {
                self::assertArrayNotHasKey('jsonSchema', $body);
                self::assertArrayNotHasKey('text', $body);
                self::assertArrayNotHasKey('response_format', $body);
            }
        }
    }

    /** @dataProvider invalidPackets */
    public function testProviderSchemaDoesNotReplaceLocalValidation(string $model, string $failure): void
    {
        $r = $this->runFixture([
            'model' => $model, 'variables' => ['folder_id' => 'fixture-folder'], 'validate' => false,
            'failAt' => 2, 'failure' => $failure,
        ]);
        self::assertSame(\RuntimeException::class, $r['type']);
        self::assertSame(2, $r['networkCalls']);
        self::assertArrayNotHasKey('result', $r);
        self::assertStringContainsString('batch 2/3 failed; 1 earlier batch(es) completed', $r['message']);
    }

    public static function invalidPackets(): array
    {
        $cases = [];
        foreach (['xai:grok-4.7', 'yandex:gpt-lite'] as $model) {
            foreach (['missing', 'extra', 'empty', 'type', 'duplicate', 'http'] as $failure) {
                $cases[] = [$model, $failure];
            }
        }
        return $cases;
    }

    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/segment_batches.php'],
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
