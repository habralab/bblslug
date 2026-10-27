<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\SegmentBatches;
use Bblslug\Translation\SegmentPlan;
use PHPUnit\Framework\TestCase;

final class SegmentSourceContractTest extends TestCase
{
    /** @dataProvider invalidSources */
    public function testSourceFailuresPreserveMessages(
        string $consumer,
        string $payload,
        string $message,
        ?string $previousMessage
    ): void {
        try {
            $this->consume($consumer, $payload);
            self::fail('Expected an invalid source exception');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame($previousMessage, $exception->getPrevious()?->getMessage());
        }
    }

    /** @return iterable<string, array{string, string, string, ?string}> */
    public static function invalidSources(): iterable
    {
        $overflow = 's' . PHP_INT_MAX . '0';
        foreach (['batches', 'plan'] as $consumer) {
            yield $consumer . ' syntax' => [$consumer, '{', 'Invalid segment JSON', 'Syntax error'];
            yield $consumer . ' root' => [$consumer, '[]', 'Segment JSON must be an object', null];
            yield $consumer . ' invalid id before value' => [
                $consumer, '{"s0":"","bad":"ok"}', 'Segment JSON contains an invalid segment id', null,
            ];
            yield $consumer . ' duplicate before empty' => [
                $consumer, '{"s0":"ok","s0":""}', 'Invalid segment source payload',
                'Duplicate HTML segment ids',
            ];
            yield $consumer . ' empty value' => [
                $consumer, '{"s0":""}', 'Invalid segment source payload',
                'HTML segments require nonempty translated strings',
            ];
            foreach (['0', '-1', (string) PHP_INT_MAX] as $id) {
                yield $consumer . ' numeric id ' . $id => [
                    $consumer, '{"' . $id . '":"ok"}', 'Segment JSON contains an invalid segment id', null,
                ];
            }
        }
        yield 'plan overflow before invalid id' => [
            'plan', '{"' . $overflow . '":"ok","bad":"ok"}',
            'Segment JSON contains an out-of-range segment id', null,
        ];
        yield 'plan invalid id before overflow' => [
            'plan', '{"bad":"ok","' . $overflow . '":"ok"}',
            'Segment JSON contains an invalid segment id', null,
        ];
    }

    /** @dataProvider validSources */
    public function testValidSourcesPreserveOutput(string $consumer, string $payload, array $expected): void
    {
        self::assertSame($expected, $this->consume($consumer, $payload));
    }

    /** @return iterable<string, array{string, string, list<string>}> */
    public static function validSources(): iterable
    {
        foreach (['batches', 'plan'] as $consumer) {
            yield $consumer . ' empty' => [$consumer, '{}', []];
            yield $consumer . ' leading zero' => [$consumer, '{"s01":"one"}', ['{"s01":"one"}']];
        }
        $payload = '{"s' . PHP_INT_MAX . '0":"ok"}';
        yield 'batches overflow' => ['batches', $payload, [$payload]];
    }

    /** @return list<string> */
    private function consume(string $consumer, string $payload): array
    {
        if ($consumer === 'batches') {
            return SegmentBatches::split($payload, 10);
        }
        return (new SegmentPlan($payload, null, 1024))->getPayloads();
    }
}
