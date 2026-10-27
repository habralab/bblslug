<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\FilterManager;
use Bblslug\Filters\HtmlTagFilter;
use Bblslug\Filters\PlaceholderCounter;
use Bblslug\Filters\UrlFilter;
use PHPUnit\Framework\TestCase;

final class JsonMarkerShieldingTest extends TestCase
{
    /**
     * @dataProvider orders
     * @param array<int,string> $order
     */
    public function testEachStageAndManagerProtectFragmentsAndRestoreExactJson(array $order): void
    {
        $source = <<<'JSON'
{"body":"\u0020https://example.org/path \u003ccode\u003ekeep\u003c/code\u003e","literal":"\u0031@@0@@"}
JSON;
        $counter = new PlaceholderCounter();
        $filters = [];
        $prepared = $source;
        foreach ($order as $name) {
            $filter = $name === 'url' ? new UrlFilter() : new HtmlTagFilter('code');
            $filters[] = $filter;
            $prepared = $filter->apply($prepared, $counter);
            json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
        }
        $decoded = json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('https://example.org', $decoded['body']);
        self::assertStringNotContainsString('<code>', $decoded['body']);
        foreach (array_reverse($filters) as $filter) {
            $prepared = $filter->restore($prepared);
        }
        self::assertSame($source, $prepared);

        $manager = new FilterManager($order);
        $prepared = $manager->apply($source);
        json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($source, $manager->restore($prepared));
        self::assertSame(1, $manager->getStats()[0]['count']);
        self::assertSame(1, $manager->getStats()[1]['count']);
    }

    public function testSourceLiteralMarkersInKeysAndValuesCannotRestoreAsUrls(): void
    {
        $marker = '\\u0031@@0@@';
        $sources = [
            '{"' . $marker . '":"fixed","url":"https://example.org/path"}',
            '{"literal":"' . $marker . '","url":"https://example.org/path"}',
            '{"' . $marker . '":"' . $marker . '","https://example.org/key":"unchanged",'
                . '"url":"https://example.org/path"}',
        ];
        foreach ($sources as $source) {
            $manager = new FilterManager(['url']);
            $prepared = $manager->apply($source);
            $decoded = json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
            $urlMarker = array_key_first($manager->getDebugMaps()[0]['map']);
            self::assertArrayNotHasKey($urlMarker, $decoded);
            self::assertNotSame($urlMarker, $decoded[array_key_first($decoded)]);
            self::assertSame($source, $manager->restore($prepared));
            self::assertSame(1, $manager->getStats()[0]['count']);
        }
    }

    public function testFullyEncodedMarkerKeepsItsExistingPreparedSpelling(): void
    {
        $source = <<<'JSON'
{"\u0040\u00405\u0040\u0040":"fixed","url":"https://example.org/path"}
JSON;
        $manager = new FilterManager(['url']);
        $prepared = $manager->apply($source);
        $expected = str_replace('https://example.org/path', '@@0@@', $source);
        self::assertSame($expected, $prepared);
        self::assertSame($source, $manager->restore($prepared));
    }

    /**
     * @dataProvider orders
     * @param array<int,string> $order
     */
    public function testNestedEncodedJsonRemainsValidAtEveryStage(array $order): void
    {
        $inner = <<<'JSON'
{"body":"\u0020https://example.org/path \u003ccode\u003ekeep\u003c/code\u003e","literal":"\u0031@@0@@"}
JSON;
        $source = json_encode(['embedded' => $inner], JSON_THROW_ON_ERROR);
        $counter = new PlaceholderCounter();
        $filters = [];
        $prepared = $source;
        foreach ($order as $name) {
            $filter = $name === 'url' ? new UrlFilter() : new HtmlTagFilter('code');
            $filters[] = $filter;
            $prepared = $filter->apply($prepared, $counter);
            $outer = json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
            $decoded = json_decode($outer['embedded'], true, 512, JSON_THROW_ON_ERROR);
        }
        self::assertStringNotContainsString('https://example.org', $decoded['body']);
        self::assertStringNotContainsString('<code>', $decoded['body']);
        foreach (array_reverse($filters) as $filter) {
            $prepared = $filter->restore($prepared);
        }
        self::assertSame($source, $prepared);
    }

    /** @return array<string,array{array<int,string>}> */
    public static function orders(): array
    {
        return [
            'url then code' => [['url', 'html_code']],
            'code then url' => [['html_code', 'url']],
        ];
    }
}
