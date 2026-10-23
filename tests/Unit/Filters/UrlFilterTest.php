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

    /**
     * @test
     * @dataProvider jsonEncodingFlags
     */
    public function masksUrlInsideJsonHtmlAttributeWithoutCorruptingJson(int $flags): void
    {
        $f = new UrlFilter();
        $ctr = new PlaceholderCounter();
        $in = json_encode(
            ['content' => '<a href="https://example.org/docs?a=1">Documentation</a>'],
            JSON_THROW_ON_ERROR | $flags
        );

        $masked = $f->apply($in, $ctr);
        $decoded = json_decode($masked, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('<a href="@@0@@">Documentation</a>', $decoded['content']);
        $this->assertSame(1, $f->getStats()['count']);
        $this->assertSame($in, $f->restore($masked));
    }

    /**
     * @test
     * @dataProvider nestedJsonEncodingFlags
     */
    public function masksUrlInsideNestedJsonAtDifferentEscapeDepths(int $innerFlags, int $outerFlags): void
    {
        $f = new UrlFilter();
        $ctr = new PlaceholderCounter();
        $nested = json_encode(
            ['html' => '<a href="https://example.org/nested">Nested</a>'],
            JSON_THROW_ON_ERROR | $innerFlags
        );
        $in = json_encode(
            ['embedded' => $nested],
            JSON_THROW_ON_ERROR | $outerFlags
        );

        $masked = $f->apply($in, $ctr);
        $decoded = json_decode($masked, true, 512, JSON_THROW_ON_ERROR);
        $decodedNested = json_decode($decoded['embedded'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('<a href="@@0@@">Nested</a>', $decodedNested['html']);
        $this->assertSame(1, $f->getStats()['count']);
        $this->assertSame($in, $f->restore($masked));
    }

    /** @test */
    public function preservesSingleQuotedHtmlAttributeBoundary(): void
    {
        $f = new UrlFilter();
        $ctr = new PlaceholderCounter();
        $in = "<a href='https://example.org/docs'>Documentation</a>";

        $masked = $f->apply($in, $ctr);

        $this->assertSame("<a href='@@0@@'>Documentation</a>", $masked);
        $this->assertSame(1, $f->getStats()['count']);
        $this->assertSame($in, $f->restore($masked));
    }

    /** @test */
    public function masksStandardMailtoUrl(): void
    {
        $f = new UrlFilter();
        $ctr = new PlaceholderCounter();
        $in = 'Contact mailto:user@example.org for details.';

        $masked = $f->apply($in, $ctr);

        $this->assertSame('Contact @@0@@ for details.', $masked);
        $this->assertSame(1, $f->getStats()['count']);
        $this->assertSame($in, $f->restore($masked));
    }

    /** @dataProvider longUrlSources */
    public function testLongUrlsDoNotExhaustTheRegexStack(string $source): void
    {
        $filter = new UrlFilter();
        $prepared = $filter->apply($source, new PlaceholderCounter());
        self::assertSame('Before @@0@@ after', $prepared);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(1, $filter->getStats()['count']);
    }

    /** @return array<string,array{string}> */
    public static function longUrlSources(): array
    {
        return [
            'plain path' => ['Before https://example.test/' . str_repeat('x', 100000) . ' after'],
            'escaped slashes' => ['Before https:\/\/example.test/' . str_repeat('part\/', 10000) . ' after'],
            'nested escapes' => ['Before https:\\\/\\\/example.test/' . str_repeat('part\\\/', 10000) . ' after'],
        ];
    }

    public function testRegexFailureDoesNotSilentlyReturnUnprotectedUrls(): void
    {
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1');
        try {
            $filter = new UrlFilter();
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Could not protect URL fragments');
            $filter->apply('https://example.test/path', new PlaceholderCounter());
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
    }

    /**
     * @return array<string,array{int}>
     */
    public static function jsonEncodingFlags(): array
    {
        return [
            'default escaped slashes' => [0],
            'unescaped slashes' => [JSON_UNESCAPED_SLASHES],
        ];
    }

    /**
     * @return array<string,array{int,int}>
     */
    public static function nestedJsonEncodingFlags(): array
    {
        return [
            'both levels escape slashes' => [0, 0],
            'inner level leaves slashes unescaped' => [JSON_UNESCAPED_SLASHES, 0],
            'outer level leaves slashes unescaped' => [0, JSON_UNESCAPED_SLASHES],
            'both levels leave slashes unescaped' => [JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_SLASHES],
        ];
    }
}
