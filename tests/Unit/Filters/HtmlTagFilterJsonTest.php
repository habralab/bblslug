<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\FilterManager;
use Bblslug\Filters\HtmlTagFilter;
use Bblslug\Filters\PlaceholderCounter;
use PHPUnit\Framework\TestCase;

final class HtmlTagFilterJsonTest extends TestCase
{
    /** @dataProvider encodedSources */
    public function testProtectsEncodedHtmlAndRestoresExactSource(int $flags, int $depth): void
    {
        $html = '<div title="a > b and </div>">Keep 😀 <div>inner "quoted" \\ code</div>tail</div>';
        $source = json_encode(['body' => 'Before ' . $html . ' after'], $flags | JSON_THROW_ON_ERROR);
        for ($i = 0; $i < $depth; $i++) {
            $source = json_encode(['embedded' => $source, 'on' => true], $flags | JSON_THROW_ON_ERROR);
        }
        $filter = new HtmlTagFilter('div');
        $prepared = $filter->apply($source, new PlaceholderCounter());
        $decoded = json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
        for ($i = 0; $i < $depth; $i++) {
            self::assertTrue($decoded['on']);
            $decoded = json_decode($decoded['embedded'], true, 512, JSON_THROW_ON_ERROR);
        }
        self::assertSame('Before @@0@@ after', $decoded['body']);
        self::assertSame(1, $filter->getStats()['count']);
        self::assertSame($source, $filter->restore($prepared));
    }

    /** @return array<string,array{int,int}> */
    public static function encodedSources(): array
    {
        $cases = [];
        $encodings = [0, JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE,
            JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS];
        foreach ($encodings as $flags) {
            foreach ([0, 1, 3] as $depth) {
                $cases[$flags . ':' . $depth] = [$flags, $depth];
            }
        }
        return $cases;
    }

    public function testPreservesKeysScalarSpellingAndUnrelatedEscapes(): void
    {
        $source = <<<'JSON'
{ "<code>KEY</code>":"\u0054ranslate", "id":9223372036854775809,
"decimal":0.12345678901234567891,"exponent":1e+20,"flag":true,"empty":null,
"body":"Prefix \uD83D\uDE00 <code>Keep\n\"quoted\"<\/code> \u0061fter" }
JSON;
        json_decode($source, false, 512, JSON_THROW_ON_ERROR);
        $filter = new HtmlTagFilter('code');
        $prepared = $filter->apply($source, new PlaceholderCounter());
        $expected = str_replace('<code>Keep\n\"quoted\"<\/code>', '@@0@@', $source);
        self::assertSame($expected, $prepared);
        self::assertSame($source, $filter->restore($prepared));
        self::assertSame(1, $filter->getStats()['count']);
    }

    public function testSeparateJsonValuesNeverFormOneHtmlBlock(): void
    {
        $source = '{"first":"<code>open", "second":"close<\\/code>"}';
        $filter = new HtmlTagFilter('code');
        self::assertSame($source, $filter->apply($source, new PlaceholderCounter()));
        self::assertSame(0, $filter->getStats()['count']);
    }

    public function testRootStringsAndRepeatedEncodedRootStringsRoundTrip(): void
    {
        $source = 'Before <code>secret</code> after';
        $filter = new HtmlTagFilter('code');
        $counter = new PlaceholderCounter();
        for ($depth = 1; $depth <= 4; $depth++) {
            $source = json_encode($source, JSON_THROW_ON_ERROR);
            $prepared = $filter->apply($source, $counter);
            $decoded = $prepared;
            for ($i = 0; $i < $depth; $i++) {
                $decoded = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            }
            self::assertMatchesRegularExpression('/^Before @@[0-9]+@@ after$/', $decoded);
            self::assertSame($source, $filter->restore($prepared));
        }
    }

    public function testLiteralMarkersAndUrlFiltersKeepBothOrdersInEncodedContainers(): void
    {
        $source = json_encode([
            'label' => 'Literal @@0@@',
            'embedded' => json_encode([
                'html' => '<code>Keep @@42@@ https://inside.example/path</code> https://outside.example/path',
            ], JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);
        foreach ([['url', 'html_code'], ['html_code', 'url']] as $filters) {
            $manager = new FilterManager($filters);
            $prepared = $manager->apply($source);
            self::assertStringNotContainsString('Keep', $prepared);
            self::assertStringNotContainsString('https:', $prepared);
            self::assertSame($source, $manager->restore($prepared));
            $codeIndex = array_search('html_code', $filters, true);
            self::assertSame(1, $manager->getStats()[$codeIndex]['count']);
        }
    }
}
