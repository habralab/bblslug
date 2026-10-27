<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models;

use Bblslug\Models\TranslationMarkers;
use PHPUnit\Framework\TestCase;

final class TranslationMarkersTest extends TestCase
{
    public function testWrapUsesExactMarkersAndPreservesText(): void
    {
        self::assertSame('‹‹TRANSLATION››', TranslationMarkers::START);
        self::assertSame('‹‹END››', TranslationMarkers::END);
        self::assertSame("‹‹TRANSLATION››\n  first\nsecond  \n‹‹END››", TranslationMarkers::wrap("  first\nsecond  "));
        self::assertSame("‹‹TRANSLATION››\n\n‹‹END››", TranslationMarkers::wrap(''));
    }

    public function testExtractsMultilineContentAndTrimsAsciiEdges(): void
    {
        $input = "prefix ‹‹TRANSLATION›› \t\n\r\0\x0Bfirst\n second \t\n\r\0\x0B‹‹END›› suffix";
        self::assertSame("first\n second", TranslationMarkers::extract($input));
    }

    public function testPreservesUnicodeWhitespace(): void
    {
        self::assertSame(
            "\u{00A0}text\u{2003}",
            TranslationMarkers::extract("‹‹TRANSLATION››\u{00A0}text\u{2003}‹‹END››")
        );
    }

    public function testEmptyPairsAreDifferentFromMissingPairs(): void
    {
        self::assertSame('', TranslationMarkers::extract('‹‹TRANSLATION››‹‹END››'));
        self::assertSame('', TranslationMarkers::extract("‹‹TRANSLATION›› \t\n\0‹‹END››"));
        self::assertNull(TranslationMarkers::extract('no markers'));
        self::assertNull(TranslationMarkers::extract('‹‹TRANSLATION››unfinished'));
        self::assertNull(TranslationMarkers::extract('unfinished‹‹END››'));
        self::assertNull(TranslationMarkers::extract('‹‹translation››text‹‹end››'));
    }

    public function testFirstCompletePairWinsAndNestedStartIsContent(): void
    {
        self::assertSame(
            'first',
            TranslationMarkers::extract('‹‹TRANSLATION››first‹‹END››‹‹TRANSLATION››second‹‹END››')
        );
        self::assertSame('outer ‹‹TRANSLATION››inner', TranslationMarkers::extract(
            '‹‹TRANSLATION››outer ‹‹TRANSLATION››inner‹‹END››tail‹‹END››'
        ));
    }
}
