<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\FilterManager;
use Bblslug\Filters\HtmlTagFilter;
use Bblslug\Filters\PlaceholderCounter;
use PHPUnit\Framework\TestCase;

final class HtmlTagFilterNestingTest extends TestCase
{
    public function testProtectsOutermostNestedBlocksAndAdjacentBlocksVerbatim(): void
    {
        $source = 'before <div data-x="1"><div class=\'inner\'>first</div>tail</div>'
            . ' between <div>second</div> after';
        $filter = new HtmlTagFilter('div');

        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame('before @@0@@ between @@1@@ after', $prepared);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(2, $filter->getStats()['count']);
    }

    public function testFakeTagsInOpaqueContextsAndAttributesDoNotAffectSelectedBlocks(): void
    {
        $source = '<!-- <div>comment fake</div> -->'
            . '<![CDATA[<div>CDATA fake</div>]]>'
            . '<p title="<div>quoted fake</div>">outside</p>'
            . '<script>const x = "<div>script fake</div>";</script>'
            . '<style>.x::before { content: "<div>style fake</div>"; }</style>'
            . '<textarea><div>textarea fake</div></textarea>'
            . '<title><div>title fake</div></title>'
            . '<xmp><div>xmp fake</div></xmp>'
            . '<iframe><div>iframe fake</div></iframe>'
            . '<noembed><div>noembed fake</div></noembed>'
            . '<noframes><div>noframes fake</div></noframes>'
            . '<div data-note="<div>attribute fake</div>">protected</div>'
            . '<plaintext><div>plaintext fake</div>';
        $filter = new HtmlTagFilter('div');

        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertStringContainsString('<!-- <div>comment fake</div> -->', $prepared);
        self::assertStringContainsString('<![CDATA[<div>CDATA fake</div>]]>', $prepared);
        self::assertStringContainsString('<script>const x = "<div>script fake</div>";</script>', $prepared);
        self::assertStringContainsString('<plaintext><div>plaintext fake</div>', $prepared);
        self::assertStringContainsString('@@0@@', $prepared);
        self::assertSame(1, $filter->getStats()['count']);
        self::assertSame($source, $filter->restore($prepared));
    }

    /** @dataProvider rawTextTagProvider */
    public function testSelectedRawTextElementIsProtectedAsOneVerbatimBlock(string $tag, string $source): void
    {
        $filter = new HtmlTagFilter($tag);

        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame('@@0@@', $prepared);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(1, $filter->getStats()['count']);
    }

    /** @return array<string,array{string,string}> */
    public static function rawTextTagProvider(): array
    {
        return [
            'script' => ['script', '<script>text <div>fake</div> and "quotes"</script>'],
            'style' => ['style', '<style>.x { content: "<div>fake</div>"; }</style>'],
            'textarea' => ['textarea', '<textarea><div>fake</div> &amp; text</textarea>'],
            'title' => ['title', '<title><div>fake</div> title</title>'],
            'xmp' => ['xmp', '<xmp><div>fake</div> xmp</xmp>'],
            'iframe' => ['iframe', '<iframe><div>fake</div> iframe</iframe>'],
            'noembed' => ['noembed', '<noembed><div>fake</div> noembed</noembed>'],
            'noframes' => ['noframes', '<noframes><div>fake</div> noframes</noframes>'],
        ];
    }

    public function testUnclosedChosenElementStopsFiltering(): void
    {
        $filter = new HtmlTagFilter('div');
        $source = '<p>safe</p><div>secret <span>content</span>';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unclosed selected HTML tag <div>');
        $filter->apply($source, new PlaceholderCounter());
    }

    public function testUnclosedOuterBlockStopsFiltering(): void
    {
        $source = '<div>outer<div>inner</div>tail';
        $filter = new HtmlTagFilter('div');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unclosed selected HTML tag <div>');
        $filter->apply($source, new PlaceholderCounter());
    }

    public function testLongTextRemainsProtectedWithALowBacktrackingLimit(): void
    {
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1000');
        try {
            $source = '<div>' . str_repeat('word ', 40000) . '</div>';
            $filter = new HtmlTagFilter('div');
            self::assertSame('@@0@@', $filter->apply($source, new PlaceholderCounter()));
            self::assertSame($source, $filter->restore('@@0@@'));
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
    }

    public function testOrphanClosingTagsAreIgnored(): void
    {
        $source = '</div>before </span><div>keep</div> after </div>';
        $filter = new HtmlTagFilter('div');

        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame('</div>before </span>@@0@@ after </div>', $prepared);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(1, $filter->getStats()['count']);
    }

    public function testNonVoidSelfClosingSyntaxStillOpensChosenElement(): void
    {
        $source = 'before <div/>secret <div>nested</div> tail</div> after';
        $filter = new HtmlTagFilter('div');

        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame('before @@0@@ after', $prepared);
        self::assertSame($source, $filter->restore($prepared));
    }

    public function testVoidChosenTagWithoutPairedBlockKeepsExistingBehavior(): void
    {
        $source = 'before <br>visible after <br/>tail';
        $filter = new HtmlTagFilter('br');

        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame($source, $prepared);
        self::assertSame(0, $filter->getStats()['count']);
    }

    public function testMalformedUnclosedRawTextOutsideChosenBlockDoesNotParseFakeSelectedTags(): void
    {
        $source = '<script>unclosed <div>fake protected text</div>'
            . '<p>translated outside raw text</p>';
        $filter = new HtmlTagFilter('div');

        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame($source, $prepared);
        self::assertSame(0, $filter->getStats()['count']);
    }

    public function testHandlesFiveThousandNestedElementsWithoutRecursiveRegex(): void
    {
        $source = str_repeat('<div>', 5000) . 'secret' . str_repeat('</div>', 5000);
        $filter = new HtmlTagFilter('div');

        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame('@@0@@', $prepared);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(1, $filter->getStats()['count']);
    }

    public function testHandlesMoreThanTwoHundredKilobytesOfRegularText(): void
    {
        $source = '<div>' . str_repeat('ordinary text ', 16000) . '</div>';
        self::assertGreaterThan(200000, strlen($source));
        $filter = new HtmlTagFilter('div');

        $prepared = $filter->apply($source, new PlaceholderCounter());

        self::assertSame('@@0@@', $prepared);
        self::assertSame($source, $filter->restore($prepared));
    }

    /** @dataProvider filterOrderProvider */
    public function testFilterManagerRoundTripsBothOrdersAndLiteralMarkers(array $filters): void
    {
        $source = 'Literal @@0@@ with <div data-url="https://example.test/a?q=1">'
            . 'secret @@42@@</div> and https://outside.example/path';
        $manager = new FilterManager($filters);

        $prepared = $manager->apply($source);

        self::assertStringNotContainsString('secret', $prepared);
        self::assertStringNotContainsString('https://example.test', $prepared);
        self::assertStringNotContainsString('https://outside.example', $prepared);
        self::assertSame($source, $manager->restore($prepared));
    }

    /** @return array<string,array{array<int,string>}> */
    public static function filterOrderProvider(): array
    {
        return [
            'url then html' => [['url', 'html_div']],
            'html then url' => [['html_div', 'url']],
        ];
    }
}
