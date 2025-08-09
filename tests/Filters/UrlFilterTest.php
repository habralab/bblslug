<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use PHPUnit\Framework\TestCase;
use Bblslug\Filters\UrlFilter;
use Bblslug\Filters\PlaceholderCounter;

/**
 * UrlFilter masks URLs with placeholders and can restore them back.
 */
final class UrlFilterTest extends TestCase
{
    /** @test */
    public function masksAndRestoresMultipleUrls(): void
    {
        $f = new UrlFilter();
        $ctr = new PlaceholderCounter();

        $in = 'Go https://ex.com?q=1 and ftp://host/file and mailto://u@d';
        $masked = $f->apply($in, $ctr);

        // No raw URLs after apply
        $this->assertStringNotContainsString('https://ex.com', $masked);
        $this->assertStringNotContainsString('ftp://host/file', $masked);

        // Placeholders present and distinct
        $this->assertMatchesRegularExpression('/@@\d+@@/', $masked);
        $this->assertNotSame(false, strpos($masked, '@@0@@'));
        $this->assertNotSame(false, strpos($masked, '@@1@@'));

        // restore must produce the exact original text
        $this->assertSame($in, $f->restore($masked));

        // stats
        $stats = $f->getStats();
        $this->assertSame('url', $stats['filter']);
        $this->assertSame(3, $stats['count']);
    }

    /** @test */
    public function nonUrlTextIsLeftAsIs(): void
    {
        $f = new UrlFilter();
        $ctr = new PlaceholderCounter();

        $in = 'no links here';
        $this->assertSame($in, $f->apply($in, $ctr));
        $this->assertSame($in, $f->restore($in));
        $this->assertSame(0, $f->getStats()['count']);
    }
}
