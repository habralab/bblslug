<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\HtmlTextView;
use PHPUnit\Framework\TestCase;

final class HtmlTextViewTest extends TestCase
{
    /** @dataProvider viewCases */
    public function testTextAndByteBoundaries(string $raw, string $decoded, array $boundaries): void
    {
        $view = new HtmlTextView($raw);
        self::assertSame($decoded, $view->getText());
        $length = strlen($decoded);
        foreach (range(0, $length) as $offset) {
            self::assertSame($boundaries[$offset] ?? null, $view->sourceOffset($offset), "offset $offset");
        }
        foreach (range($length, 0) as $offset) {
            self::assertSame($boundaries[$offset] ?? null, $view->sourceOffset($offset), "reverse offset $offset");
        }
        self::assertNull($view->sourceOffset(-1));
        self::assertNull($view->sourceOffset($length + 1));
        self::assertSame($boundaries[0], $view->sourceOffset(0));
    }

    /** @return array<string,array{string,string,array<int,int>}> */
    public static function viewCases(): array
    {
        return [
            'empty' => ['', '', [0 => 0]],
            'ASCII Unicode and newline' => ["Aé\n🙂", "Aé\n🙂", [0 => 0, 1 => 1, 3 => 3, 4 => 4, 8 => 8]],
            'entities and unknown candidate' => [
                'a&amp;&#x41;&#65;&bogus;z', 'a&AA&bogus;z',
                [0 => 0, 1 => 1, 2 => 6, 3 => 12, 4 => 17, 11 => 24, 12 => 25],
            ],
            'multiple codepoint entities' => [
                '&ThickSpace;&NotEqualTilde;', "\u{205F}\u{200A}\u{2242}\u{338}",
                [0 => 0, 6 => 12, 11 => 27],
            ],
            'encoded marker' => [
                '&#64;&#64;4&#64;&#64;', '@@4@@', [0 => 0, 1 => 5, 2 => 10, 3 => 11, 4 => 16, 5 => 21],
            ],
            'non-candidate entity spelling' => ['&a;', '&a;', [0 => 0, 1 => 1, 2 => 2, 3 => 3]],
        ];
    }

    public function testRejectsInvalidUtf8(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('HTML segment transport requires UTF-8 text');
        new HtmlTextView("a\xC3\x28");
    }
}
