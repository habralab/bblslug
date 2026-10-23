<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\FilterManager;
use Bblslug\Filters\PlaceholderCounter;
use Bblslug\Filters\UrlFilter;
use PHPUnit\Framework\TestCase;

final class UrlFilterUnicodeJsonTest extends TestCase
{
    public function testMasksUnicodeEscapedUrlInJsonWhilePreservingExactSourceBytes(): void
    {
        $urlSource = '\u0068\u0074\u0074\u0070\u0053\u003a\u002f\u002F'
            . '\u0065\u0078\u00e1\u006d\u0070\u006c\u0065\u002e\u006f\u0072\u0067'
            . '\u002f\u0063\u0061f\u00e9\u003f\u0071\u003d\u00e9';
        $source = '{"url":"' . $urlSource . '","keep":"\u005C\u006E\u0020\u006eot a url"}';
        json_decode($source, true, 512, JSON_THROW_ON_ERROR);

        $filter = new UrlFilter();
        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame(str_replace($urlSource, '@@0@@', $source), $prepared);
        $decoded = json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('@@0@@', $decoded['url']);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(1, $filter->getStats()['count']);
    }

    public function testUnicodeHostPathAndSurrogatePrefixInQuoteEscapedHtmlAttribute(): void
    {
        $source = <<<'JSON'
{"body":"\uD83D\uDE00 <a href=\"https:\/\/ex\u00e1mple.test\/caf\u00e9?q=\u00e9\">quoted</a>"}
JSON;
        json_decode($source, true, 512, JSON_THROW_ON_ERROR);

        $filter = new UrlFilter();
        $prepared = $filter->apply($source, new PlaceholderCounter());
        $decoded = json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('😀 <a href="@@0@@">quoted</a>', $decoded['body']);
        self::assertStringNotContainsString('https:', $decoded['body']);
        self::assertSame($source, $filter->restore($prepared));
    }

    public function testOnlyJsonStringValuesAreFilteredAndEverythingElseKeepsItsSpelling(): void
    {
        $source = <<<'JSON'
{
  "\u0075rl": "\u0068\u0074\u0074\u0070\u003a\u002f\u002fexample.test\/path?x=1",
  "https:\/\/key.example": "unchanged",
  "number": 12345678901234567890,
  "decimal": 1.2300e+02,
  "flag": true,
  "empty": null,
  "unrelated": "line\\n quote\\\" slash\\/ literal"
}
JSON;
        json_decode($source, true, 512, JSON_THROW_ON_ERROR);

        $filter = new UrlFilter();
        $prepared = $filter->apply($source, new PlaceholderCounter());
        $expected = str_replace(
            '\\u0068\\u0074\\u0074\\u0070\\u003a\\u002f\\u002fexample.test\\/path?x=1',
            '@@0@@',
            $source
        );

        self::assertSame($expected, $prepared);
        $decoded = json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('@@0@@', $decoded['url']);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(1, $filter->getStats()['count']);
    }

    public function testUrlDoesNotSpanSeparateJsonStringValues(): void
    {
        $sources = [
            '{"first":"https://","second":"host.test"}',
            '["https://","host.test"]',
        ];

        foreach ($sources as $source) {
            $filter = new UrlFilter();
            self::assertSame($source, $filter->apply($source, new PlaceholderCounter()));
            self::assertSame(0, $filter->getStats()['count']);
        }
    }

    public function testRootStringsAndThreeOrMoreJsonEncodingLayersExposeMarkersInDecodedValues(): void
    {
        $source = 'Before https://example.test/path?q=1 after';
        $filter = new UrlFilter();
        $counter = new PlaceholderCounter();

        for ($depth = 1; $depth <= 4; $depth++) {
            $source = json_encode($source, JSON_THROW_ON_ERROR);
            $prepared = $filter->apply($source, $counter);
            $decoded = $prepared;
            for ($i = 0; $i < $depth; $i++) {
                $decoded = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            }
            self::assertSame('Before @@' . ($depth - 1) . '@@ after', $decoded);
            self::assertStringNotContainsString('https://', $decoded);
            self::assertSame($source, $filter->restore($prepared));
        }
        self::assertSame(4, $filter->getStats()['count']);
    }

    public function testPreparedOuterAndEncodedInnerJsonValuesDecodeToMaskedValuesAndRestoreExactly(): void
    {
        $inner = json_encode([
            'html' => '<a href="https://inside.example/path?q=1">link</a>',
            'tail' => 'https://tail.example/',
        ], JSON_THROW_ON_ERROR);
        $source = json_encode(['embedded' => $inner, 'label' => 'ok'], JSON_THROW_ON_ERROR);

        $filter = new UrlFilter();
        $prepared = $filter->apply($source, new PlaceholderCounter());
        $outer = json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
        $decodedInner = json_decode($outer['embedded'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('<a href="@@0@@">link</a>', $decodedInner['html']);
        self::assertSame('@@1@@', $decodedInner['tail']);
        self::assertStringNotContainsString('https://', $outer['embedded']);
        self::assertSame($source, $filter->restore($prepared));
    }

    public function testFilterManagerOrdersKeepLiteralMarkersMeaningfulAndRestoreExactJsonSource(): void
    {
        $source = json_encode([
            'label' => 'Literal @@0@@',
            'html' => '<code>Keep @@42@@ https://inside.example/path</code> https://outside.example/path',
        ], JSON_THROW_ON_ERROR);
        $source = str_replace('https:', '\\u0068ttps\\u003a', $source);

        foreach ([['url', 'html_code'], ['html_code', 'url']] as $order) {
            $manager = new FilterManager($order);
            $prepared = $manager->apply($source);
            self::assertStringNotContainsString('https:', $prepared);
            self::assertStringNotContainsString('outside.example', $prepared);
            self::assertStringNotContainsString('<code>', $prepared);
            self::assertSame($source, $manager->restore($prepared));
            self::assertStringContainsString('Literal @@0@@', $manager->restore($prepared));
        }
    }

    public function testLongUnicodeJsonEncodedUrlOverFiftyKilobytesRemainsProtected(): void
    {
        $encodedUrl = 'https\u003a\u002f\u002fexample.test\u002f' . str_repeat('\u0078', 10000);
        $source = '{"url":"' . $encodedUrl . '"}';
        self::assertGreaterThan(50000, strlen($source));
        $filter = new UrlFilter();
        $prepared = $filter->apply($source, new PlaceholderCounter());
        $decoded = json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('@@0@@', $decoded['url']);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(1, $filter->getStats()['count']);
    }

    public function testPlaintextUnicodeEscapeNotationIsNotDecodedOutsideValidJson(): void
    {
        $source = 'literal \\u0068\\u0074\\u0074\\u0070\\u003a\\u002f\\u002fexample.test/path';
        $filter = new UrlFilter();

        self::assertSame($source, $filter->apply($source, new PlaceholderCounter()));
        self::assertSame(0, $filter->getStats()['count']);
    }

    public function testRawHtmlWithEscapedSlashesRetainsExistingUrlSupport(): void
    {
        $source = '<a href="https:\/\/example.test\/docs?q=1">Documentation</a>';
        $filter = new UrlFilter();
        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame('<a href="@@0@@">Documentation</a>', $prepared);
        self::assertSame($source, $filter->restore($prepared));
    }
}
