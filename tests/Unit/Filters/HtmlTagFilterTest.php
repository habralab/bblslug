<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use PHPUnit\Framework\TestCase;
use Bblslug\Filters\HtmlTagFilter;
use Bblslug\Filters\PlaceholderCounter;

/**
 * HtmlTagFilter replaces selected tag blocks with placeholders and restores them.
 */
final class HtmlTagFilterTest extends TestCase
{
    /** @test */
    public function masksAndRestoresTagBlocks(): void
    {
        $f = new HtmlTagFilter('a');
        $ctr = new PlaceholderCounter();

        $in = 'x <a href="u">T1</a> y <a href="v"><span>T2</span></a> z';
        $masked = $f->apply($in, $ctr);

        // Whole <a>...</a> blocks must be replaced by placeholders
        $this->assertStringNotContainsString('<a ', $masked);
        $this->assertMatchesRegularExpression('/@@\d+@@/', $masked);

        // restore() must bring back original HTML verbatim
        $this->assertSame($in, $f->restore($masked));

        $stats = $f->getStats();
        $this->assertSame('html_a', $stats['filter']);
        $this->assertSame(2, $stats['count']);
    }

    /** @test */
    public function unrelatedTagsRemain(): void
    {
        $f = new HtmlTagFilter('code');
        $ctr = new PlaceholderCounter();

        $in = '<p><em>hi</em></p>';
        $masked = $f->apply($in, $ctr);

        // No <code> blocks -> input unchanged
        $this->assertSame($in, $masked);
        $this->assertSame($in, $f->restore($masked));
        $this->assertSame(0, $f->getStats()['count']);
    }

    /** @dataProvider exactTagProvider */
    public function testOnlyTheExactTagNameIsProtected(string $tag, string $source, string $expected): void
    {
        $filter = new HtmlTagFilter($tag);
        $prepared = $filter->apply($source, new PlaceholderCounter());
        self::assertSame($expected, $prepared);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(1, $filter->getStats()['count']);
    }

    /** @return array<string,array{string,string,string}> */
    public static function exactTagProvider(): array
    {
        return [
            'prefix' => ['pre', '<prelude>Translate</prelude><pre>Keep</pre>', '<prelude>Translate</prelude>@@0@@'],
            'custom tag' => ['code', '<code-block>Translate</code-block><code>Keep</code>',
                '<code-block>Translate</code-block>@@0@@'],
            'namespace' => ['code', '<code:sample>Translate</code:sample><code>Keep</code>',
                '<code:sample>Translate</code:sample>@@0@@'],
            'short name' => ['a', '<abbr>Translate</abbr><a href="url">Keep</a>', '<abbr>Translate</abbr>@@0@@'],
            'closing whitespace' => ['code', "Before <CODE\tclass=\"sample\">Keep</CoDe \n> after",
                'Before @@0@@ after'],
            'literal name' => ['x.y', '<xAy>Translate</xAy><x.y>Keep</x.y>', '<xAy>Translate</xAy>@@0@@'],
        ];
    }

    public function testAnUnrelatedPrefixDoesNotMatchEvenWithItsOwnClosingTag(): void
    {
        $filter = new HtmlTagFilter('pre');
        $source = '<prelude>Translate</prelude>';
        self::assertSame($source, $filter->apply($source, new PlaceholderCounter()));
        self::assertSame(0, $filter->getStats()['count']);
    }

    public function testRegexFailureDoesNotSilentlySendUnprotectedContent(): void
    {
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1000');
        try {
            $filter = new HtmlTagFilter('code');
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Could not protect HTML tag fragments');
            $filter->apply('<code ' . str_repeat('a="x" ', 5000) . '>Keep</code>', new PlaceholderCounter());
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
    }
}
