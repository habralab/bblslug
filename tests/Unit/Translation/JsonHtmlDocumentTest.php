<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\JsonHtmlDocument;
use PHPUnit\Framework\TestCase;

final class JsonHtmlDocumentTest extends TestCase
{
    public function testEmptyPointerSelectsAStringDocumentRoot(): void
    {
        $document = new JsonHtmlDocument('  "<p>Root</p>"  ', ['']);

        self::assertSame('{"s0":"Root"}', $document->getJson());
        self::assertSame('  "<p>Raiz</p>"  ', $document->restore('{"s0":"Raiz"}'));
    }

    public function testExtractsSelectedStringsInSourceOrderAndRestoresOnlyThoseStrings(): void
    {
        $source = '{"untouched" : 9223372036854775809, "first":"<p>Hello <b>one</b></p>",'
            . '"nested":["<i>Two</i>"],"a/b":"<em>Three</em>","~key":"<u>Four</u>",'
            . '"":"<span>Five</span>","spelling":"<mark>Six</mark>"}';
        $document = new JsonHtmlDocument(
            $source,
            ['/first', '/nested/0', '/a~1b', '/~0key', '/', '/spelling', '/first']
        );

        self::assertSame(
            '{"s0":"Hello","s1":"one","s2":"Two","s3":"Three","s4":"Four","s5":"Five","s6":"Six"}',
            $document->getJson()
        );
        $translated = $document->restore(
            '{"s0":"Hola","s1":"uno","s2":"Dos","s3":"Tres","s4":"Cuatro","s5":"Cinco","s6":"Seis"}'
        );
        self::assertSame(
            '{"untouched" : 9223372036854775809, "first":"<p>Hola <b>uno</b></p>",'
            . '"nested":["<i>Dos</i>"],"a/b":"<em>Tres</em>","~key":"<u>Cuatro</u>",'
            . '"":"<span>Cinco</span>","spelling":"<mark>Seis</mark>"}',
            $translated
        );
    }

    public function testPreparedJsonFiltersOnlySelectedDecodedHtmlAndUsesUniqueMarkers(): void
    {
        $source = '{"before" : "<code>untouched</code>", "one":"<div>A <code>x</code></div>",'
            . '"two":"<div>B <code>y</code></div>", "after" : 1e+20}';
        $document = new JsonHtmlDocument($source, ['/one', '/two'], ['html_code']);

        $prepared = $document->getPrepared();
        self::assertStringContainsString('{"before" : "<code>untouched</code>", "one":"<div>A ', $prepared);
        self::assertStringContainsString('","two":"<div>B ', $prepared);
        self::assertStringContainsString('", "after" : 1e+20}', $prepared);
        self::assertStringNotContainsString('>x</code>', $prepared);
        self::assertStringNotContainsString('>y</code>', $prepared);
        self::assertSame([['filter' => 'html_code', 'count' => 2]], $document->getFilterStats());
        self::assertTrue($document->hasTexts());
    }

    public function testMarkerLikeJsonKeyIsNotShieldedAndOnlyItsSelectedValueIsFiltered(): void
    {
        $source = '{"@@0@@":"<p>See https://example.test/a <code>x</code></p>",'
            . '"other":"<code>keep</code>","raw":"@@0@@"}';
        $document = new JsonHtmlDocument($source, ['/' . '@@0@@'], ['url', 'html_code']);
        $prepared = $document->getPrepared();
        self::assertStringContainsString('"@@0@@":', $prepared);
        self::assertStringContainsString('"other":"<code>keep</code>","raw":"@@0@@"', $prepared);
        self::assertStringNotContainsString('https://example.test/a', $prepared);
        self::assertStringNotContainsString('>x</code>', $prepared);
        self::assertSame(
            '<p>See https://example.test/a <code>x</code></p>',
            json_decode($document->restore('{"s0":"See"}'), true, 512, JSON_THROW_ON_ERROR)['@@0@@']
        );
    }

    public function testFilterStatsIncludeProtectedHtmlWithoutVisibleTextAndEmptySelectionHasNoTexts(): void
    {
        $withOnlyOpaqueText = new JsonHtmlDocument(
            '{"html":"<div><code>fixed</code></div>"}',
            ['/html'],
            ['html_code']
        );
        self::assertSame('{"html":"<div>@@0@@</div>"}', $withOnlyOpaqueText->getPrepared());
        self::assertSame([['filter' => 'html_code', 'count' => 1]], $withOnlyOpaqueText->getFilterStats());
        self::assertFalse($withOnlyOpaqueText->hasTexts());

        $empty = new JsonHtmlDocument('{"n":3,"html":"<pre>fixed</pre>"}', ['/html']);
        self::assertSame('{}', $empty->getJson());
        self::assertFalse($empty->hasTexts());
    }

    public function testEscapedSourceStringsAreDecodedForHtmlButUnselectedTokensStayByteExact(): void
    {
        $source = '{"keep":"\\u003Ckeep\\u003E", "html":"\\u003Cp\\u003EHello\\u003C/p\\u003E",'
            . '"number":9223372036854775809,"exp":1e+20}';
        $document = new JsonHtmlDocument($source, ['/html']);
        self::assertSame('{"s0":"Hello"}', $document->getJson());
        self::assertSame($source, $document->restore($document->getJson()));
        $restored = $document->restore('{"s0":"Bonjour"}');
        self::assertStringContainsString('"keep":"\\u003Ckeep\\u003E"', $restored);
        self::assertStringContainsString('"number":9223372036854775809,"exp":1e+20', $restored);
        self::assertSame('<p>Bonjour</p>', json_decode($restored, true, 512, JSON_THROW_ON_ERROR)['html']);
    }

    public function testEncodedLiteralMarkersAndPlaintextAreScopedToTheirSelectedString(): void
    {
        $source = '{"plain":"<plaintext>Hello <b>world", "html":"<p>send '
            . '&#64;&#64;1&#64;&#64; <code>x</code></p>"}';
        $document = new JsonHtmlDocument($source, ['/plain', '/html'], ['html_code']);

        self::assertSame('{"s0":"send"}', $document->getJson());
        $prepared = $document->getPrepared();
        self::assertStringContainsString('"plain":"<plaintext>Hello <b>world"', $prepared);
        self::assertStringContainsString('&#64;&#64;1&#64;&#64; @@', $prepared);
        self::assertSame(
            '{"plain":"<plaintext>Hello <b>world", "html":"<p>translated &#64;&#64;1&#64;&#64; <code>x</code></p>"}',
            $document->restore('{"s0":"translated"}')
        );
        $restored = json_decode($document->restore('{"s0":"translated"}'), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString(
            '@@1@@',
            html_entity_decode($restored['html'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        self::assertStringContainsString('<code>x</code>', $restored['html']);
        self::assertSame('<plaintext>Hello <b>world', $restored['plain']);
    }

    /** @dataProvider invalidTranslations */
    public function testRestoreRejectsEveryMalformedOrNonStrictFlatTranslation(string $translation): void
    {
        $document = new JsonHtmlDocument('{"html":"<p>Hello</p>"}', ['/html']);
        $this->expectException(\RuntimeException::class);
        $document->restore($translation);
    }

    /** @return list<array{string}> */
    public static function invalidTranslations(): array
    {
        return array_map(static fn (string $json): array => [$json], [
            '{}', '[]', 'null', 'invalid', '{"s0":null}', '{"s0":[]}', '{"s0":true}', '{"s0":1}',
            '{"s0":""}', '{"s0":"@@123@@"}', '{"s0":"a","extra":"b"}',
            '{"s0":"a","s0":"b"}', '{"s0":"a","\\u00730":"b"}',
        ]);
    }

    /** @dataProvider invalidPointers */
    public function testInvalidMissingWrongTypeAndMalformedPointersFailBeforeTranslation(string $pointer): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JsonHtmlDocument('{"html":"<p>Hello</p>","count":3}', [$pointer]);
    }

    /** @return list<array{string}> */
    public static function invalidPointers(): array
    {
        return [['/missing'], ['/count'], ['html'], ['/bad~2'], ['/0']];
    }

    public function testDuplicateSourceKeysAreRejectedBeforeSelection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JsonHtmlDocument('{"html":"<p>one</p>","html":"<p>two</p>"}', ['/html']);
    }
}
