<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\FilterManager;
use Bblslug\Filters\HtmlTagFilter;
use Bblslug\Filters\PlaceholderCounter;
use Bblslug\Filters\UrlFilter;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for source text that already contains filter markers.
 */
final class PlaceholderCollisionTest extends TestCase
{
    /** @test */
    public function managerRoundTripsLiteralMarkerAlongsideUrl(): void
    {
        $manager = new FilterManager(['url']);
        $source = 'Literal @@0@@ beside https://example.test/path';

        $masked = $manager->apply($source);

        $this->assertMatchesRegularExpression('/@@\d+@@/', $masked);
        $this->assertStringNotContainsString('https://example.test/path', $masked);
        $this->assertSame($source, $manager->restore($masked));
        $this->assertSame(1, $manager->getStats()[0]['count']);
    }

    /** @test */
    public function literalMarkersInsideUrlsRoundTripAsUrlContent(): void
    {
        $manager = new FilterManager(['url']);
        $source = 'Visit https://example.test/path/@@0@@1@@ and mailto:user@@0@@@example.test.';

        $masked = $manager->apply($source);

        $this->assertStringNotContainsString('https://example.test', $masked);
        $this->assertStringNotContainsString('mailto:user', $masked);
        $this->assertSame($source, $manager->restore($masked));
        $this->assertSame(2, $manager->getStats()[0]['count']);
    }

    /**
     * @test
     * @dataProvider markerBoundarySources
     */
    public function consecutiveAndIncompleteMarkersRoundTrip(string $source): void
    {
        $manager = new FilterManager(['url']);

        $masked = $manager->apply($source);

        $this->assertSame($source, $manager->restore($masked));
        $this->assertSame(1, $manager->getStats()[0]['count']);
    }

    /**
     * @test
     * @dataProvider filterOrders
     * @param array<int,string> $filters
     */
    public function urlAndProtectedHtmlRoundTripInEitherOrder(array $filters, string $source): void
    {
        $manager = new FilterManager($filters);

        $masked = $manager->apply($source);

        $this->assertSame($source, $manager->restore($masked));
        $stats = $manager->getStats();
        $this->assertSame(1, $stats[array_search('url', $filters, true)]['count']);
        $htmlFilter = array_values(array_filter($filters, static fn (string $filter): bool => $filter !== 'url'))[0];
        $this->assertSame(1, $stats[array_search($htmlFilter, $filters, true)]['count']);
    }

    /** @test */
    public function managerRestoresOlderAndNewerInputsAfterBothWereApplied(): void
    {
        $manager = new FilterManager(['url']);
        $oldSource = 'old https://old.example.test';
        $newSource = 'new literal @@0@@ and https://new.example.test';

        $oldMasked = $manager->apply($oldSource);
        $newMasked = $manager->apply($newSource);

        $this->assertSame($oldSource, $manager->restore($oldMasked));
        $this->assertSame($newSource, $manager->restore($newMasked));
        $this->assertSame(2, $manager->getStats()[0]['count']);
    }

    /** @test */
    public function directUrlFilterRestoresOlderAndNewerInputsWithSharedCounter(): void
    {
        $filter = new UrlFilter();
        $counter = new PlaceholderCounter();
        $oldSource = 'old https://old.example.test';
        $newSource = 'new literal @@0@@ and https://new.example.test';

        $oldMasked = $filter->apply($oldSource, $counter);
        $newMasked = $filter->apply($newSource, $counter);

        $this->assertSame($oldSource, $filter->restore($oldMasked));
        $this->assertSame($newSource, $filter->restore($newMasked));
        $this->assertSame(2, $filter->getStats()['count']);
    }

    /** @test */
    public function directHtmlFilterRestoresOlderAndNewerInputsWithSharedCounter(): void
    {
        $filter = new HtmlTagFilter('code');
        $counter = new PlaceholderCounter();
        $oldSource = 'old <code>old</code>';
        $newSource = 'new literal @@0@@ and <code>new</code>';

        $oldMasked = $filter->apply($oldSource, $counter);
        $newMasked = $filter->apply($newSource, $counter);

        $this->assertSame($oldSource, $filter->restore($oldMasked));
        $this->assertSame($newSource, $filter->restore($newMasked));
        $this->assertSame(2, $filter->getStats()['count']);
    }

    /** @test */
    public function restoredHtmlContentContainingLiteralMarkerIsNotExpandedAgain(): void
    {
        $manager = new FilterManager(['url', 'html_code']);
        $source = 'https://example.test <code>literal @@0@@</code>';

        $masked = $manager->apply($source);

        $this->assertSame($source, $manager->restore($masked));
        $this->assertSame(1, $manager->getStats()[0]['count']);
        $this->assertSame(1, $manager->getStats()[1]['count']);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function markerBoundarySources(): array
    {
        return [
            'consecutive markers' => ['@@0@@1@@ https://example.test/path'],
            'incomplete marker before url' => ['@@0@https://example.test/path'],
        ];
    }

    /**
     * @return array<string,array{array<int,string>,string}>
     */
    public static function filterOrders(): array
    {
        return [
            'url then code' => [
                ['url', 'html_code'],
                'https://example.test <code>literal @@0@@</code>',
            ],
            'code then url' => [
                ['html_code', 'url'],
                '<code>literal @@0@@</code> https://example.test',
            ],
            'url then anchor' => [
                ['url', 'html_a'],
                'https://example.test <a href="/path">literal @@0@@</a>',
            ],
            'anchor then url' => [
                ['html_a', 'url'],
                '<a href="/path">literal @@0@@</a> https://example.test',
            ],
        ];
    }
}
