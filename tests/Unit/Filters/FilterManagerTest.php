<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use PHPUnit\Framework\TestCase;
use Bblslug\Filters\FilterManager;

/**
 * FilterManager integration with real filters (UrlFilter + HtmlTagFilter).
 */
final class FilterManagerTest extends TestCase
{
    /** @test */
    public function appliesFiltersInConfiguredOrderAndRestoresBack(): void
    {
        // Order matters: first mask URLs, then mask <a> tags.
        $fm = new FilterManager(['url', 'html_a']);

        $in = 'Read <a href="https://example.com/page?id=1">link</a> or mailto://x@y.';
        $masked = $fm->apply($in);

        // After apply() there should be placeholders @@0@@, @@1@@...
        $this->assertMatchesRegularExpression('/@@\d+@@/', $masked);
        $this->assertStringNotContainsString('https://example.com', $masked);
        $this->assertStringNotContainsString('<a ', $masked);

        // restore() must bring the exact original text back
        $restored = $fm->restore($masked);
        $this->assertSame($in, $restored);

        // Stats should contain one entry per filter with proper counts
        $stats = $fm->getStats();
        $this->assertCount(2, $stats);

        // url filter should count 2 URLs (https + mailto)
        $this->assertSame('url', $stats[0]['filter']);
        $this->assertSame(2, $stats[0]['count']);

        // html_a should count 1 masked <a> node
        $this->assertSame('html_a', $stats[1]['filter']);
        $this->assertSame(1, $stats[1]['count']);
    }

    /** @test */
    public function managerIsReusableAcrossDifferentInputs(): void
    {
        $fm = new FilterManager(['url']);

        $masked1 = $fm->apply('a https://a.tld b');
        $masked2 = $fm->apply('c https://b.tld d');

        // Placeholders advance monotonically within one FilterManager instance
        $this->assertStringContainsString('@@0@@', $masked1);
        $this->assertStringContainsString('@@1@@', $masked2);

        // Each masked string must restore to its own original
        $this->assertSame('a https://a.tld b', $fm->restore($masked1));
        $this->assertSame('c https://b.tld d', $fm->restore($masked2));

        $stats = $fm->getStats();
        $this->assertSame('url', $stats[0]['filter']);
        $this->assertSame(2, $stats[0]['count']); // two URLs across two apply() calls
    }
}
