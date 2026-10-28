<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Translation;

use Bblslug\Translation\BatchCheckpoint;
use Bblslug\Translation\SegmentBatchState;
use PHPUnit\Framework\TestCase;

final class SegmentBatchStateTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bblslug-state-' . bin2hex(random_bytes(5));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testRetainedAndFreshResultsHaveDistinctUsageAndReuseAccounting(): void
    {
        $path = $this->directory . '/state.json';
        $checkpoint = new BatchCheckpoint($path, 'fingerprint', 2);
        $saved = ['text' => '{"s1":"ancien"}', 'httpStatus' => 201, 'consumed' => ['tokens' => ['total' => 7]]];
        $checkpoint->append(0, $saved);
        $state = new SegmentBatchState(
            ['usage' => ['tokens' => ['total' => 'total']]],
            'Text',
            false,
            $checkpoint,
            null
        );
        $state->validateRetained(['{"s1":"old"}', '{"s2":"new"}']);
        $state->acceptRetained(0, '{"s1":"old"}', $saved);
        $state->acceptFresh(1, '{"s2":"new"}', [
            'text' => '{"s2":"nouveau"}', 'usage' => ['total' => 3], 'httpStatus' => 202,
        ]);

        $fresh = ['text' => '{"s2":"nouveau"}', 'httpStatus' => 202, 'consumed' => ['tokens' => ['total' => 3]]];
        self::assertSame($fresh, $checkpoint->get(1));
        // Read the file while the checkpoint is still locked: acceptance must already be durable.
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $first = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
        $second = json_decode($lines[2], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([$saved, $fresh], [$first['record'], $second['record']]);
        $result = $state->result(true);
        self::assertSame('{"s1":"ancien","s2":"nouveau"}', $result['text']);
        self::assertSame(['tokens' => ['total' => 10]], $result['consumed']);
        self::assertSame(['tokens' => ['total' => 3]], $result['consumedThisRun']);
        self::assertSame(1, $result['reusedBatches']);
        self::assertSame([
            ['index' => 1, 'segments' => 1, 'httpStatus' => 201, 'consumed' => $saved['consumed'], 'reused' => true],
            ['index' => 2, 'segments' => 1, 'httpStatus' => 202, 'consumed' => $fresh['consumed'], 'reused' => false],
        ], $result['batches']);
        self::assertArrayNotHasKey('reused', $state->result(false)['batches'][0]);
        self::assertArrayNotHasKey('reused', $state->result(false)['batches'][1]);
    }

    public function testInvalidFreshTranslationDoesNotAppendToCheckpoint(): void
    {
        $path = $this->directory . '/state.json';
        $checkpoint = new BatchCheckpoint($path, 'fingerprint', 1);
        $state = new SegmentBatchState([], 'Text', false, $checkpoint, null);
        $before = file_get_contents($path);
        foreach (['{"s1":"one","s1":"two"}', '{"s2":"wrong"}', '{"s1":""}', '{"s1":"line\\nbreak"}'] as $text) {
            try {
                $state->acceptFresh(0, '{"s1":"source"}', ['text' => $text, 'usage' => null, 'httpStatus' => 200]);
                self::fail('Expected invalid segment translation to be rejected');
            } catch (\RuntimeException) {
                self::assertNull($checkpoint->get(0));
                self::assertSame($before, file_get_contents($path));
            }
        }
        self::assertSame([], $state->result(true)['batches']);
    }

    public function testEmptyUsageRemainsEmptyForFreshAndRetainedResults(): void
    {
        $state = new SegmentBatchState([], 'Text', true, null, null);
        $state->acceptRetained(0, '{"s1":"source"}', [
            'text' => '{"s1":"first"}', 'httpStatus' => 200, 'consumed' => [],
        ]);
        $state->acceptFresh(1, '{"s2":"source"}', ['text' => '{"s2":"second"}', 'usage' => null, 'httpStatus' => 200]);
        $result = $state->result(true);
        self::assertSame([], $result['consumed']);
        self::assertSame([], $result['consumedThisRun']);
        self::assertSame([[], []], array_column($result['batches'], 'consumed'));
        self::assertSame([true, false], array_column($result['batches'], 'reused'));
    }
}
