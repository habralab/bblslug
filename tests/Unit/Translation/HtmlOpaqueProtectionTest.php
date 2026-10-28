<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\HtmlTextDocument;
use PHPUnit\Framework\TestCase;

final class HtmlOpaqueProtectionTest extends TestCase
{
    public function testNestedOpaqueElementsRestoreInBothOrders(): void
    {
        $html = '<pre>a<code>b</code><svg><math>x</math></svg></pre>'
            . '<svg><pre>p</pre><script>t</script></svg><code>x<pre>y</pre></code><p>end</p>';
        $document = new HtmlTextDocument($html);
        self::assertSame('{"s0":"end"}', $document->getJson());
        self::assertSame(str_replace('<p>end</p>', '<p>fin</p>', $html), $document->restore('{"s0":"fin"}'));
        self::assertSame($html, $document->restore($document->getJson()));
    }

    public function testDenseLiteralMarkersSurviveInTextAndOpaqueElements(): void
    {
        $html = '<pre>@@0@@ @@1@@</pre><code>@@2@@</code>'
            . '<p>one @@0@@ two @@@ 123@@ @@123 0@@0@@3 @@999@@ @@0@@@@1@@ end</p>';
        $document = new HtmlTextDocument($html);
        self::assertSame('{"s0":"one","s1":"two","s2":"end"}', $document->getJson());
        self::assertSame(
            '<pre>@@0@@ @@1@@</pre><code>@@2@@</code>'
            . '<p>un @@0@@ deux @@@ 123@@ @@123 0@@0@@3 @@999@@ @@0@@@@1@@ fin</p>',
            $document->restore('{"s0":"un","s1":"deux","s2":"fin"}')
        );
        self::assertSame($html, $document->restore($document->getJson()));
    }

    public function testDigitsAdjacentToOpaqueElementsRestoreUnchanged(): void
    {
        $html = '1<pre>x</pre>2<code>y</code>3<svg/>4';
        $document = new HtmlTextDocument($html);
        self::assertSame('{}', $document->getJson());
        self::assertSame($html, $document->restore('{}'));
        self::assertSame($html, $document->restore('{}'));
    }

    public function testMarkersInAttributesAndRcdataPreserveSource(): void
    {
        $html = '<p title="hello @@0@@ world" aria-label=hey>text</p>'
            . '<title>hi <code>x</code> @@4@@</title>';
        $document = new HtmlTextDocument($html);
        self::assertSame(
            '{"s0":"hello","s1":"world","s2":"hey","s3":"text","s4":"hi <code>x</code>"}',
            $document->getJson()
        );
        self::assertSame(
            '<p title="bonjour @@0@@ monde" aria-label=salut>texte</p><title>titre @@4@@</title>',
            $document->restore('{"s0":"bonjour","s1":"monde","s2":"salut","s3":"texte","s4":"titre"}')
        );
        self::assertSame($html, $document->restore($document->getJson()));
    }

    public function testJsonLookingSourceKeepsEscapesAndLiteralMarkers(): void
    {
        $html = '{"a":"<pre>hello</pre> @@0@@", "b":"<p>world</p>", "@@1@@":"x"}';
        $document = new HtmlTextDocument($html);
        self::assertSame(
            '{"s0":"{\\"a\\":\\"","s1":"\\", \\"b\\":\\"","s2":"world",'
            . '"s3":"\\", \\"","s4":"\\":\\"x\\"}"}',
            $document->getJson()
        );
        $values = json_decode($document->getJson(), true, 512, JSON_THROW_ON_ERROR);
        $values['s2'] = 'monde';
        self::assertSame(str_replace('world', 'monde', $html), $document->restoreValues($values));
        self::assertSame($html, $document->restore($document->getJson()));
    }

    public function testJsonLookingEscapesSurviveOpaqueContent(): void
    {
        $html = '{"text":"<code>line\\n\\\\"quoted\\\\" @@23@@</code> end","next":"\\u0040\\u0040"}';
        $document = new HtmlTextDocument($html);
        self::assertSame(
            json_encode((object) [
                's0' => '{"text":"',
                's1' => 'end","next":"\\u0040\\u0040"}',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $document->getJson()
        );
        self::assertSame($html, $document->restore($document->getJson()));
    }

    /** @dataProvider lateInvalidElements */
    public function testLateIncompleteOpaqueElementsFail(string $html, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        new HtmlTextDocument($html);
    }

    /** @return list<array{string,string}> */
    public static function lateInvalidElements(): array
    {
        return [
            ['<pre>good</pre><code>fine</code><svg', 'Incomplete opening tag for selected HTML filter <svg>'],
            ['<pre>good</pre><noscript>bad', 'Unclosed selected HTML tag <noscript>'],
        ];
    }
}
