<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\JsonStringView;
use PHPUnit\Framework\TestCase;

final class JsonStringViewTest extends TestCase
{
    public function testDecodesAndMapsEscapes(): void
    {
        $view = new JsonStringView('"a\\n\\u00E9\\uD83D\\uDE00z"');
        self::assertSame("a\né😀z", $view->getText());
        self::assertSame(1, $view->sourceOffset(0));
        self::assertSame(2, $view->sourceOffset(1));
        self::assertSame(4, $view->sourceOffset(2));
        self::assertSame(4, $view->sourceOffset(3));
        self::assertSame(10, $view->sourceOffset(4));
        self::assertSame(10, $view->sourceOffset(7));
        self::assertSame(22, $view->sourceOffset(8));
    }
    public function testMapsRawUtf8AndEmptyString(): void
    {
        $view = new JsonStringView('"é😀"');
        self::assertSame('é😀', $view->getText());
        self::assertSame(1, $view->sourceOffset(0));
        self::assertSame(7, $view->sourceOffset(strlen($view->getText())));
        $empty = new JsonStringView('""');
        self::assertSame(1, $empty->sourceOffset(0));
    }

    public function testMixedOffsetsInDifferentLookupOrders(): void
    {
        $view = new JsonStringView('"é\\u00E9\\\\u0041\\uD83D\\uDE00z"');
        self::assertSame('éé\\u0041😀z', $view->getText());
        $offsets = [1, 2, 3, 3, 9, 11, 12, 13, 14, 15, 16, 16, 16, 16, 28, 29];
        $ascending = range(0, count($offsets) - 1);
        $random = [10, 3, 15, 1, 12, 5, 0, 14, 7, 2, 13, 6, 9, 4, 11, 8];
        foreach ([$ascending, array_reverse($ascending), $random] as $order) {
            foreach ($order as $offset) {
                self::assertSame($offsets[$offset], $view->sourceOffset($offset));
            }
        }
    }

    public function testOutOfRangeOffsetsHaveStableExceptionMessage(): void
    {
        foreach ([new JsonStringView('""'), new JsonStringView('"x"')] as $view) {
            foreach ([-1, strlen($view->getText()) + 1] as $offset) {
                try {
                    $view->sourceOffset($offset);
                    self::fail('Expected an exception for an out-of-range offset');
                } catch (\InvalidArgumentException $exception) {
                    self::assertSame('Decoded byte offset is outside the string', $exception->getMessage());
                }
            }
        }
    }

    public function testOffsetLookupDoesNotRetainView(): void
    {
        $wasEnabled = gc_enabled();
        gc_disable();
        try {
            $view = new JsonStringView('"a\\u00E9b"');
            $view->sourceOffset(1);
            $reference = \WeakReference::create($view);
            unset($view);
            self::assertNull($reference->get());
        } finally {
            if ($wasEnabled) {
                gc_enable();
            }
        }
    }

    public function testSupportsEverySimpleEscape(): void
    {
        $token = '"\\"\\\\\\/\\b\\f\\n\\r\\t"';
        $view = new JsonStringView($token);
        self::assertSame('"' . "\\/" . chr(8) . "\f\n\r\t", $view->getText());
        self::assertSame(1, $view->sourceOffset(0));
        self::assertSame(3, $view->sourceOffset(1));
        self::assertSame(5, $view->sourceOffset(2));
        self::assertSame(7, $view->sourceOffset(3));
        self::assertSame(9, $view->sourceOffset(4));
        self::assertSame(11, $view->sourceOffset(5));
        self::assertSame(13, $view->sourceOffset(6));
        self::assertSame(15, $view->sourceOffset(7));
        self::assertSame(17, $view->sourceOffset(8));
    }

    public function testMapsEscapedHtmlBoundariesAndSurrogatePrefix(): void
    {
        $view = new JsonStringView('"\\uD83D\\uDE00\\u003Cb>code</b>"');
        self::assertSame('😀<b>code</b>', $view->getText());
        self::assertSame(1, $view->sourceOffset(0));
        self::assertSame(1, $view->sourceOffset(3));
        self::assertSame(13, $view->sourceOffset(4));
        self::assertSame(19, $view->sourceOffset(5));
        self::assertSame(29, $view->sourceOffset(strlen($view->getText())));
    }
    public function testLongStringAndInvalidInputs(): void
    {
        $source = str_repeat('x', 200001);
        $view = new JsonStringView(json_encode($source, JSON_THROW_ON_ERROR));
        self::assertSame($source, $view->getText());
        self::assertSame(strlen($source) + 1, $view->sourceOffset(strlen($source)));
        try {
            new JsonStringView('123');
            self::fail();
        } catch (\InvalidArgumentException) {
        }
        try {
            new JsonStringView('"\\uD800"');
            self::fail();
        } catch (\InvalidArgumentException) {
        }
        try {
            new JsonStringView('"unterminated');
            self::fail();
        } catch (\InvalidArgumentException) {
        }
        $this->expectException(\InvalidArgumentException::class);
        $view->sourceOffset(-1);
    }
}
