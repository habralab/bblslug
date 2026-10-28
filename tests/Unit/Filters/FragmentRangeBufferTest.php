<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\FragmentProtection;
use Bblslug\Filters\HtmlTagFilter;
use Bblslug\Filters\PlaceholderCounter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FragmentRangeBufferTest extends TestCase
{
    /** @dataProvider malformedHtmlProvider */
    public function testLateMalformedHtmlKeepsEarlierCapturesAndShieldingState(string $source): void
    {
        $filter = new HtmlTagFilter('div');
        $counter = new PlaceholderCounter();
        $prepared = $filter->apply('<div>previous</div>', $counter);
        $map = $filter->debugDump();

        try {
            $filter->apply($source, $counter);
            self::fail('Expected an unclosed tag error');
        } catch (RuntimeException $error) {
            self::assertSame('Unclosed selected HTML tag <div>', $error->getMessage());
        }

        self::assertSame($map, $filter->debugDump());
        self::assertSame(['filter' => 'html_div', 'count' => 1], $filter->getStats());
        self::assertSame(2, $counter->current());
        self::assertSame('<div>previous</div>', $filter->restore($prepared));
        self::assertSame('@@9@@', $filter->restore('@@1@@'));
        self::assertSame('@@2@@', $filter->apply('<div>next</div>', $counter));
        self::assertSame(3, $counter->current());
    }

    /** @return array<string,array{string}> */
    public static function malformedHtmlProvider(): array
    {
        return [
            'plain' => ['@@9@@ <div>valid</div> <div>unclosed'],
            'json values' => ['["<div>valid</div>","@@9@@ <div>unclosed"]'],
        ];
    }

    public function testThrowingRangeIteratorLeavesPriorCapturesAndCounterUnchanged(): void
    {
        $protection = new FragmentProtection();
        $counter = new PlaceholderCounter();
        self::assertSame('@@0@@', $protection->capture('previous', [[0, 8]], $counter));
        $map = $protection->getCaptures();
        $ranges = (static function (): \Generator {
            yield [0, 3];
            throw new RuntimeException('scanner failed');
        })();

        try {
            $protection->capture('one two', $ranges, $counter);
            self::fail('Expected scanner failure');
        } catch (RuntimeException $error) {
            self::assertSame('scanner failed', $error->getMessage());
        }

        self::assertSame(1, $counter->current());
        self::assertSame($map, $protection->getCaptures());
        self::assertSame('previous', $protection->restore('@@0@@'));
    }

    public function testArrayAndIteratorRangesCaptureIdenticalSourceBytes(): void
    {
        $source = "é <div>one</div> and <div>two</div>";
        $ranges = [[3, 14], [22, 14]];
        $arrayProtection = new FragmentProtection();
        $iteratorProtection = new FragmentProtection();
        $arrayOutput = $arrayProtection->capture($source, $ranges, new PlaceholderCounter());
        $iteratorOutput = $iteratorProtection->capture(
            $source,
            (static function () use ($ranges): \Generator {
                yield from $ranges;
            })(),
            new PlaceholderCounter()
        );

        self::assertSame("é @@0@@ and @@1@@", $arrayOutput);
        self::assertSame(['@@0@@' => '<div>one</div>', '@@1@@' => '<div>two</div>'], $arrayProtection->getCaptures());
        self::assertSame($arrayOutput, $iteratorOutput);
        self::assertSame($arrayProtection->getCaptures(), $iteratorProtection->getCaptures());
        self::assertSame($source, $arrayProtection->restore($arrayOutput));
        self::assertSame($source, $iteratorProtection->restore($iteratorOutput));
    }
}
