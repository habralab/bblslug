<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugSegmentBatchesTest extends TestCase
{
    public function testSequentialPacketsPreserveGlobalIdsAndSumUsage(): void
    {
        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash'] as $model) {
            $r = $this->runFixture(['model' => $model]);
            self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
            self::assertSame('<p>T:One</p><p>T:Two</p><p>T:Three</p>', $r['result']['result']);
            self::assertSame([['s0' => 'One'], ['s1' => 'Two'], ['s2' => 'Three']], $r['packets']);
            self::assertSame(3, $r['networkCalls']);
            self::assertSame(30, $r['result']['consumed']['tokens']['total']);
            self::assertCount(3, $r['result']['batches']);
            $raw = json_decode($r['result']['rawResponseBody'], true, 512, JSON_THROW_ON_ERROR);
            self::assertCount(3, $raw);
            foreach ($raw as $response) {
                self::assertIsArray(json_decode($response, true, 512, JSON_THROW_ON_ERROR));
            }
        }
    }

    public function testJsonModePreservesEverythingOutsideSelectedHtmlAcrossPackets(): void
    {
        $source = '{"keep" : 1.0000,"big":900719925474099312345,"body":"<p>One</p><p>Two</p><p>Three</p>"}';
        $r = $this->runFixture(['input' => $source, 'format' => 'json', 'htmlJsonPointers' => ['/body']]);
        self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
        self::assertSame(
            str_replace(['>One<', '>Two<', '>Three<'], ['>T:One<', '>T:Two<', '>T:Three<'], $source),
            $r['result']['result']
        );
        self::assertSame(3, $r['networkCalls']);
    }

    /** @dataProvider failures */
    public function testBadSecondPacketStopsImmediatelyWithoutReturningPartialDocument(string $failure): void
    {
        $r = $this->runFixture(['failAt' => 2, 'failure' => $failure, 'validate' => false]);
        self::assertSame(\RuntimeException::class, $r['type']);
        self::assertSame(2, $r['networkCalls']);
        self::assertArrayNotHasKey('result', $r);
        self::assertStringContainsString('batch 2/3 failed; 1 earlier', $r['message']);
    }

    public static function failures(): array
    {
        return [['http'], ['missing'], ['extra'], ['empty'], ['type'], ['duplicate']];
    }

    public function testAllLengthAndRequestLimitsAreCheckedBeforeFirstHttp(): void
    {
        $r = $this->runFixture(['model' => 'deepl:free', 'input' => '<p>One</p><p>' . str_repeat('x', 31000) . '</p>']);
        self::assertSame(\RuntimeException::class, $r['type']);
        self::assertSame(0, $r['networkCalls']);
        self::assertStringContainsString('batch 2/2', $r['message']);
        $r = $this->runFixture([
            'model' => 'deepl:free', 'variables' => ['json_transport' => 'text_batch'],
            'input' => '<p>One</p><p>' . str_repeat('😀', 3000) . '</p>', 'context' => str_repeat('c', 125000),
        ]);
        self::assertSame(\InvalidArgumentException::class, $r['type']);
        self::assertSame(0, $r['networkCalls']);
    }

    public function testDeepLDocumentStateIsIndependentForEveryPacket(): void
    {
        $r = $this->runFixture(['model' => 'deepl:free']);
        self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
        self::assertSame('<p>T:One</p><p>T:Two</p><p>T:Three</p>', $r['result']['result']);
        self::assertSame(9, $r['networkCalls']);
        self::assertCount(3, $r['result']['batches']);
        self::assertSame([], $r['result']['consumed']);
    }

    public function testDryRunAndNoTextCauseNoNetwork(): void
    {
        foreach ([['dryRun' => true], ['input' => '<code>fixed</code>']] as $scenario) {
            $r = $this->runFixture($scenario);
            self::assertSame('success', $r['type'], (string) ($r['message'] ?? ''));
            self::assertSame(0, $r['networkCalls']);
            self::assertSame($scenario['input'] ?? '<p>One</p><p>Two</p><p>Three</p>', $r['result']['result']);
        }
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
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
