<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\DebuggableFilterInterface;
use Bblslug\Filters\FilterManager;
use Bblslug\Filters\HtmlTagFilter;
use Bblslug\Filters\PlaceholderCounter;
use Bblslug\Filters\UrlFilter;
use PHPUnit\Framework\TestCase;

final class FilterDebugMapsTest extends TestCase
{
    public function testBothBuiltInFiltersExposeExactSlicesWithoutModifyingRestoration(): void
    {
        $url = 'https://example.org/a?x=1&y=2';
        $code = '<code>"source" \\ keep</code>';
        foreach ([[new UrlFilter(), $url], [new HtmlTagFilter('code'), $code]] as [$filter, $source]) {
            self::assertInstanceOf(DebuggableFilterInterface::class, $filter);
            $prepared = $filter->apply($source, new PlaceholderCounter());
            self::assertSame([$prepared => $source], $filter->debugDump());
            self::assertSame($source, $filter->restore($prepared));
        }
    }

    public function testManagerMapsKeepFilterOrderAndAllowRestoringNestedCaptures(): void
    {
        $manager = new FilterManager(['url', 'html_code']);
        $source = '<code>https://example.org/test</code>';
        $prepared = $manager->apply($source);
        $maps = $manager->getDebugMaps();
        self::assertSame(['url', 'html_code'], array_column($maps, 'filter'));
        self::assertSame(['https://example.org/test'], array_values($maps[0]['map']));
        self::assertStringStartsWith('<code>@@', reset($maps[1]['map']));
        self::assertSame($source, $manager->restore($prepared));
        self::assertSame($maps, $manager->getDebugMaps());
        self::assertSame([], (new FilterManager([]))->getDebugMaps());
    }
}
