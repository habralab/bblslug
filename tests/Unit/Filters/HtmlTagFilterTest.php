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
}
