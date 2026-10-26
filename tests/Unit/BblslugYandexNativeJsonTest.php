<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugYandexNativeJsonTest extends TestCase
{
    public function testNativeJsonFormatsKeepNestedStructureAndNonStringScalars(): void
    {
        foreach (['yandex:gpt-lite', 'yandex:gpt-pro'] as $model) {
            foreach (['json', 'linedJson'] as $format) {
                $r = $this->runFixture([
                    'model' => $model, 'format' => $format,
                    'input' => json_encode([
                        'message' => 'Hello', 'nested' => ['quote' => "a\"b\nc"],
                        'count' => 1, 'enabled' => false, 'empty' => null,
                    ], JSON_THROW_ON_ERROR),
                ]);
                self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
                $output = json_decode($r['result']['result'], true, 512, JSON_THROW_ON_ERROR);
                self::assertSame('T:Hello', $output['message']);
                self::assertSame(1, $output['count']);
                self::assertFalse($output['enabled']);
                self::assertNull($output['empty']);
                self::assertTrue($r['requests'][0]['jsonObject']);
                self::assertStringStartsWith('{', $r['requests'][0]['messages'][1]['text']);
            }
        }
    }

    public function testNativeSyntaxIsMandatoryButScalarAndSchemaChecksRemainActive(): void
    {
        foreach ([true, false] as $validate) {
            $r = $this->runFixture(['translated' => "```json\n{}\n```", 'validate' => $validate]);
            self::assertSame(\RuntimeException::class, $r['type']);
            self::assertStringContainsString('native JSON response is not valid', $r['message']);
        }
        foreach (
            [
            ['{"message":"Bonjour","count":2}', 'JSON scalar values changed'],
            ['{"message":"Bonjour"}', 'Schema mismatch'],
            ] as [$translated, $expected]
        ) {
            $r = $this->runFixture(['translated' => $translated]);
            self::assertSame(\RuntimeException::class, $r['type']);
            self::assertStringContainsString($expected, $r['message']);
        }
    }

    public function testNativeSegmentsAndBatchesPreserveMarkupAndUnselectedJson(): void
    {
        $source = '{"metadata" : 1.0000,"body":"<p>One</p><p>Two</p><p>Three</p>"}';
        $r = $this->runFixture([
            'input' => $source, 'transport' => 'auto', 'htmlTransport' => 'segments',
            'htmlJsonPointers' => ['/body'], 'batchSize' => 2,
        ]);
        self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
        self::assertSame(
            str_replace(['>One<', '>Two<', '>Three<'], ['>T:One<', '>T:Two<', '>T:Three<'], $source),
            $r['result']['result']
        );
        self::assertCount(2, $r['requests']);
        self::assertSame(20, $r['result']['consumed']['tokens']['total']);
        self::assertTrue($r['requests'][0]['jsonObject']);
        self::assertTrue($r['requests'][1]['jsonObject']);
    }

    public function testExplicitNativeRejectsNonObjectInputBeforeHttp(): void
    {
        $r = $this->runFixture(['input' => '["Hello"]']);
        self::assertSame(\InvalidArgumentException::class, $r['type']);
        self::assertSame([], $r['requests']);
    }

    public function testAutoKeepsArrayRootsOnMarkerTransport(): void
    {
        $r = $this->runFixture(['transport' => 'auto', 'input' => '["Hello"]']);
        self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
        self::assertArrayNotHasKey('jsonObject', $r['requests'][0]);
        self::assertSame('["T:Hello"]', $r['result']['result']);
    }

    public function testExplicitMarkerTransportRemainsAvailable(): void
    {
        $r = $this->runFixture(['transport' => 'markers']);
        self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
        self::assertArrayNotHasKey('jsonObject', $r['requests'][0]);
        self::assertStringStartsWith('‹‹TRANSLATION››', $r['requests'][0]['messages'][1]['text']);
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/yandex_json.php'],
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
