<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use Bblslug\Filters\FilterManager;
use Bblslug\Filters\HtmlTagFilter;
use PHPUnit\Framework\TestCase;

final class FilterNamesTest extends TestCase
{
    public function testEmptyFilterListIsAllowed(): void
    {
        $manager = new FilterManager([]);

        self::assertSame('plain text', $manager->apply('plain text'));
        self::assertSame([], $manager->getStats());
    }

    public function testUnknownFilterIsRejectedWithItsName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown filter: urll');

        new FilterManager(['urll']);
    }

    /** @dataProvider invalidHtmlTagNames */
    public function testInvalidHtmlTagNameIsRejected(string $tag): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTML tag name for filter');

        new HtmlTagFilter($tag);
    }

    /** @dataProvider invalidHtmlFilterNames */
    public function testMalformedHtmlFilterNameIsRejected(string $filter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTML tag name for filter');

        new FilterManager([$filter]);
    }

    /** @dataProvider validHtmlTagNames */
    public function testCustomHtmlTagNameMasksAndRestoresItsWholePair(string $tag): void
    {
        $manager = new FilterManager(['html_' . $tag]);
        $source = '<' . $tag . '>content</' . $tag . '>';

        $masked = $manager->apply($source);

        self::assertNotSame($source, $masked);
        self::assertSame($source, $manager->restore($masked));
        self::assertSame(['filter' => 'html_' . $tag, 'count' => 1], $manager->getStats()[0]);
    }

    /** @return array<string,array{string}> */
    public static function invalidHtmlTagNames(): array
    {
        return [
            'empty' => [''],
            'must start with ASCII letter' => ['1div'],
            'unicode first character' => ['элемент'],
            'whitespace' => ['div name'],
            'less than' => ['div<'],
            'greater than' => ['div>'],
            'double quote' => ['div"'],
            'single quote' => ["div'"],
            'nul' => ["div\0"],
            'control character' => ["div\n"],
        ];
    }

    /** @return array<string,array{string}> */
    public static function invalidHtmlFilterNames(): array
    {
        return [
            'missing tag' => ['html_'],
            'space in tag' => ['html_pre bad'],
            'slash in tag' => ['html_/code'],
        ];
    }

    /** @return array<string,array{string}> */
    public static function validHtmlTagNames(): array
    {
        return [
            'hyphen' => ['x-panel'],
            'colon' => ['svg:path'],
            'dot' => ['x.y'],
            'underscore' => ['x_panel'],
            'pipe' => ['x|panel'],
            'unicode after first character' => ['x-элемент'],
        ];
    }
}
