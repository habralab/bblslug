<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\HtmlTextDocument;
use PHPUnit\Framework\TestCase;

final class HtmlTextDocumentTest extends TestCase
{
    public function testExtractsAndRestoresVisibleTextWithoutSerializingMarkup(): void
    {
        $html = '<div title="A &amp; B">Hello <b>world</b>!</div><!-- nope --><script>"ignore"</script>';
        $document = new HtmlTextDocument($html);
        self::assertSame('{"s0":"A & B","s1":"Hello","s2":"world","s3":"!"}', $document->getJson());
        self::assertSame(
            '<div title="A &amp; B">Hola <b>mundo</b>!</div><!-- nope --><script>"ignore"</script>',
            $document->restore('{"s0":"A & B","s1":"Hola","s2":"mundo","s3":"!"}')
        );
    }

    public function testRejectsMissingExtraDuplicateAndNonStringValues(): void
    {
        $document = new HtmlTextDocument('<p>Hello</p>');
        $this->expectException(\RuntimeException::class);
        $document->restore('{}');
    }

    public function testOpaqueContextsAndMarkersArePreserved(): void
    {
        $html = '<pre>do @@0@@ not</pre><p>send @@1@@ text</p><svg><text>hidden</text></svg>';
        $document = new HtmlTextDocument($html);
        self::assertSame('{"s0":"send","s1":"text"}', $document->getJson());
        self::assertSame(
            '<pre>do @@0@@ not</pre><p>translated @@1@@ text</p><svg><text>hidden</text></svg>',
            $document->restore('{"s0":"translated","s1":"text"}')
        );
    }

    public function testEmptyAndRcdataAndRawContexts(): void
    {
        self::assertSame('{}', (new HtmlTextDocument('<!-- </script> --><script/>fixed</script>'))->getJson());
        $source = '<!--x--><title>2 &lt; 3</title><textarea>a < b</textarea><xmp>keep <b>x</b></xmp>';
        self::assertSame($source, (new HtmlTextDocument($source))->restore('{"s0":"2 < 3","s1":"a < b"}'));
    }

    public function testStrictDuplicateKeysAndInjectionEscaping(): void
    {
        $document = new HtmlTextDocument('<p title="title" data-x="title=bad">x</p>');
        self::assertStringContainsString('&lt;', $document->restore('{"s0":"<x>","s1":"<y>"}'));
        $this->expectException(\RuntimeException::class);
        $document->restore('{"s0":"a","s0":"b","s1":"c"}');
    }

    /** @dataProvider preservationCases */
    public function testBytePreservationAndSafeReconstruction(
        string $html,
        array $texts,
        array $translated,
        string $expected
    ): void {
        $document = new HtmlTextDocument($html);
        self::assertSame($texts, get_object_vars(json_decode($document->getJson(), false, 512, JSON_THROW_ON_ERROR)));
        self::assertSame($html, $document->restore($document->getJson()));
        self::assertSame($expected, $document->restore(json_encode((object) $translated, JSON_THROW_ON_ERROR)));
    }

    /** @return array<string,array{string,array<string,string>,array<string,string>,string}> */
    public static function preservationCases(): array
    {
        return [
            'entity whitespace offsets' => [
                '<p>&nbsp;Hello&#32;</p>', ['s0' => 'Hello'], ['s0' => 'Bonjour'], '<p>&nbsp;Bonjour&#32;</p>',
            ],
            'Unicode entity whitespace' => [
                '<p>&ThickSpace;Hello&nbsp;</p>', ['s0' => 'Hello'],
                ['s0' => 'Bonjour'], '<p>&ThickSpace;Bonjour&nbsp;</p>',
            ],
            'literal less-than' => ['<p>a < b</p>', ['s0' => 'a < b'], ['s0' => 'c < d'], '<p>c &lt; d</p>'],
            'literal encoded markers' => [
                '<p>One &#64;&#64;4&#64;&#64; two</p>', ['s0' => 'One', 's1' => 'two'],
                ['s1' => 'deux', 's0' => 'Un'], '<p>Un &#64;&#64;4&#64;&#64; deux</p>',
            ],
            'fake attribute inside another value' => [
                '<p data-x=\' title="bad"\' title="title">body</p>', ['s0' => 'title', 's1' => 'body'],
                ['s0' => 'titre', 's1' => 'texte'], '<p data-x=\' title="bad"\' title="titre">texte</p>',
            ],
            'quoted greater-than and injection' => [
                '<p title="a > b">Hello</p>', ['s0' => 'a > b', 's1' => 'Hello'],
                ['s0' => '<img>', 's1' => '<script>'], '<p title="&lt;img&gt;">&lt;script&gt;</p>',
            ],
            'unquoted Unicode whitespace' => [
                '<img alt=Hello>', ['s0' => 'Hello'], ['s0' => "x y`=Z\u{2003}"], '<img alt=x&#32;y&#96;&#61;Z&#8195;>',
            ],
            'RCDATA' => [
                '<textarea>a <b> &amp; b</textarea>', ['s0' => 'a <b> & b'], ['s0' => '<q>'],
                '<textarea>&lt;q&gt;</textarea>',
            ],
            'nested protected tags and comments' => [
                '<pre><!-- </pre> --><code>fixed</code></pre><code>one<code>two</code>three</code><p>Hello</p>',
                ['s0' => 'Hello'], ['s0' => 'Bonjour'],
                '<pre><!-- </pre> --><code>fixed</code></pre><code>one<code>two</code>three</code><p>Bonjour</p>',
            ],
            'raw closing-name boundary and self-close' => [
                '<script/>"</scripture>"</script><style>fixed</style><p>Hello</p>', ['s0' => 'Hello'],
                ['s0' => 'Bonjour'], '<script/>"</scripture>"</script><style>fixed</style><p>Bonjour</p>',
            ],
            'foreign content' => [
                '<svg><path/><text>keep</text></svg><math><mi>keep</mi></math><p>Hello</p>', ['s0' => 'Hello'],
                ['s0' => 'Bonjour'], '<svg><path/><text>keep</text></svg><math><mi>keep</mi></math><p>Bonjour</p>',
            ],
            'empty self-closing foreign roots' => ['<svg/><math />', [], [], '<svg/><math />'],
            'foreign self-closing and nested content' => [
                '<p>Before</p><SVG data-slash="/"/><p>Middle</p><svg><svg/>fixed <SVG>inner</SVG></svg>'
                . '<p>After</p><MaTh data-slash="/" /><p>End</p><math><math/><mi>fixed</mi></math>',
                ['s0' => 'Before', 's1' => 'Middle', 's2' => 'After', 's3' => 'End'],
                ['s0' => 'Avant', 's1' => 'Milieu', 's2' => 'Après', 's3' => 'Fin'],
                '<p>Avant</p><SVG data-slash="/"/><p>Milieu</p><svg><svg/>fixed <SVG>inner</SVG></svg>'
                . '<p>Après</p><MaTh data-slash="/" /><p>Fin</p><math><math/><mi>fixed</mi></math>',
            ],
            'plaintext consumes EOF' => ['<plaintext>Hello <b>world', [], [], '<plaintext>Hello <b>world'],
            'CDATA and declarations' => [
                '<!DOCTYPE html><![CDATA[keep > <p>unchanged</p>]]><!-- keep --><p>Hello</p>',
                ['s0' => 'Hello'], ['s0' => 'Bonjour'],
                '<!DOCTYPE html><![CDATA[keep > <p>unchanged</p>]]><!-- keep --><p>Bonjour</p>',
            ],
        ];
    }

    /** @dataProvider invalidTranslations */
    public function testBrokenProviderProtocolAlwaysFails(string $output): void
    {
        $document = new HtmlTextDocument('<p>Hello</p>');
        $this->expectException(\RuntimeException::class);
        $document->restore($output);
    }

    /** @return list<array{string}> */
    public static function invalidTranslations(): array
    {
        return array_map(static fn (string $json): array => [$json], [
            '{}', '[]', 'null', 'invalid', '{"s0":null}', '{"s0":[]}', '{"s0":true}', '{"s0":1}',
            '{"s0":""}', '{"s0":"\\u00a0"}', '{"s0":"@@123@@"}', '{"s0":"a","extra":"b"}',
            '{"s0":"a","s0":"b"}', '{"s0":"a","\\u00730":"b"}',
        ]);
    }

    public function testQuotedJsonLookingTextDoesNotCreatePhantomKeys(): void
    {
        $document = new HtmlTextDocument('<p>Hello</p>');
        $value = '"s0": "one", "s0": "two"';
        $html = $document->restore(json_encode(['s0' => $value], JSON_THROW_ON_ERROR));
        self::assertSame($value, html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** @dataProvider incompleteSources */
    public function testUnsupportedOrIncompleteSourceFailsBeforeTranslation(string $html): void
    {
        $this->expectException(\Exception::class);
        new HtmlTextDocument($html);
    }

    /** @return list<array{string}> */
    public static function incompleteSources(): array
    {
        return [['<p title="oops>'], ['<!-- oops'], ['<code/>oops'], ['<textarea>oops']];
    }
}
