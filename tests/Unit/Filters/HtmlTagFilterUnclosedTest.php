<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\HtmlTagFilter;
use Bblslug\Filters\PlaceholderCounter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HtmlTagFilterUnclosedTest extends TestCase
{
    /** @dataProvider unclosedTagProvider */
    public function testUnclosedSelectedNonVoidTagFails(string $tag, string $source): void
    {
        $this->expectUnclosed($tag, $source);
    }

    /** @return array<string,array{string,string}> */
    public static function unclosedTagProvider(): array
    {
        return [
            'div' => ['div', '<div>secret'],
            'nested outer' => ['div', '<div>outer<div>inner</div>tail'],
            'non-void self-closing syntax' => ['div', '<div/>secret'],
            'pre' => ['pre', '<pre>secret'],
            'code' => ['code', '<code>secret'],
            'missing closing angle bracket' => ['div', '<div secret'],
            'unclosed attribute quote' => ['div', '<div data="secret>text'],
        ];
    }

    /** @dataProvider rawTextTagProvider */
    public function testUnclosedSelectedRawTextTagFails(string $tag): void
    {
        $this->expectUnclosed($tag, "<{$tag}>secret <div>fake</div>");
    }

    /** @return array<string,array{string}> */
    public static function rawTextTagProvider(): array
    {
        $tags = ['script', 'style', 'title', 'textarea', 'xmp', 'iframe', 'noembed', 'noframes'];
        return array_combine($tags, array_map(static fn (string $tag): array => [$tag], $tags)) ?: [];
    }

    public function testUnclosedSelectedTagInsideOpaqueContextsIsIgnored(): void
    {
        $source = '<!-- <div>comment -->'
            . '<![CDATA[<div>cdata]]>'
            . '<p data-note="<div>attribute">safe</p>'
            . '<script><div>raw text</div>';
        $filter = new HtmlTagFilter('div');

        self::assertSame($source, $filter->apply($source, new PlaceholderCounter()));
        self::assertSame(0, $filter->getStats()['count']);
    }

    /** @dataProvider voidTagProvider */
    public function testValidUnpairedVoidTagsRemainAllowed(string $tag): void
    {
        $source = "before <{$tag}>after";
        $filter = new HtmlTagFilter($tag);

        self::assertSame($source, $filter->apply($source, new PlaceholderCounter()));
        self::assertSame(0, $filter->getStats()['count']);
    }

    /** @return array<string,array{string}> */
    public static function voidTagProvider(): array
    {
        $tags = [
            'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
            'link', 'meta', 'param', 'source', 'track', 'wbr',
            'basefont', 'bgsound', 'frame', 'keygen',
        ];
        return array_combine($tags, array_map(static fn (string $tag): array => [$tag], $tags)) ?: [];
    }

    public function testPlaintextSelectedProtectsToEofByteExactly(): void
    {
        $source = '<plaintext>keep "quotes" \\ path </plaintext>'
            . '<div>also opaque';
        $filter = new HtmlTagFilter('plaintext');

        self::assertSame('@@0@@', $filter->apply($source, new PlaceholderCounter()));
        self::assertSame($source, $filter->restore('@@0@@'));
    }

    public function testJsonStringBoundariesDoNotAllowDifferentValuesToCloseEachOther(): void
    {
        $this->expectUnclosed('div', '{"first":"<div>open","second":"</div>"}');
    }

    public function testNestedEncodedJsonUsesSameEofPolicy(): void
    {
        $source = json_encode(
            ['payload' => json_encode(['html' => '<div>open'], JSON_THROW_ON_ERROR)],
            JSON_THROW_ON_ERROR
        );
        $this->expectUnclosed('div', $source);
    }

    public function testCompleteNestedJsonPairStillRoundTrips(): void
    {
        $source = json_encode(
            ['payload' => json_encode(['html' => '<div>keep</div>'], JSON_THROW_ON_ERROR)],
            JSON_THROW_ON_ERROR
        );
        $filter = new HtmlTagFilter('div');
        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertStringNotContainsString('<div>', $prepared);
        self::assertSame($source, $filter->restore($prepared));
    }

    private function expectUnclosed(string $tag, string $source): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($tag);
        (new HtmlTagFilter($tag))->apply($source, new PlaceholderCounter());
    }
}
