<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use Bblslug\Validation\HtmlStructureValidator;
use PHPUnit\Framework\TestCase;

final class HtmlStructureValidatorTest extends TestCase
{
    public function testLiteralUtf8AndEntitiesHaveEquivalentAttributeValues(): void
    {
        $source = '<p data-name="café" id="section">Hello</p>';
        $output = '<P ID="section" data-name="caf&#233;">Привет</P>';
        self::assertTrue((new HtmlStructureValidator($source))->validate($output)->isValid());
    }

    /** @dataProvider markupChanges */
    public function testMarkupChangesAreRejected(string $source, string $output): void
    {
        $result = (new HtmlStructureValidator($source))->validate($output);
        self::assertFalse($result->isValid());
        self::assertNotEmpty($result->getErrors());
    }

    /** @return array<string,array{string,string}> */
    public static function markupChanges(): array
    {
        return [
            'leading backtick fence from live Yandex response' => [
                '<div><p>Hello</p></div>', "```\n<div><p>Привет</p></div>",
            ],
            'paired html fence' => [
                '<p>Hello</p>', "```html\n<p>Привет</p>\n```",
            ],
            'trailing fence only' => ['<p>Hello</p>', "<p>Привет</p>\n```\n"],
            'tilde fence' => ['<p>Hello</p>', "~~~html\n<p>Привет</p>\n~~~"],
            'changed existing fence kind' => [
                "```html\n<p>Hello</p>\n```", "~~~html\n<p>Hallo</p>\n~~~",
            ],
            'changed existing fence length' => [
                "```html\n<p>Hello</p>\n```", "````html\n<p>Hallo</p>\n````",
            ],
            'changed existing fence label' => [
                "```html\n<p>Hello</p>\n```", "```php\n<p>Hallo</p>\n```",
            ],
            'changed closing fence length' => [
                "```html\n<p>Hello</p>\n```", "```html\n<p>Hallo</p>\n````",
            ],
            'duplicated inner fence' => [
                "```html\n<p>Hello</p>\n```", "```html\n```html\n<p>Hallo</p>\n```\n```",
            ],
            'removed inline fence' => ['<p>Hello <code>```</code></p>', '<p>Hallo <code></code></p>'],
            'changed inline fence kind' => ['<p>Hello <code>```</code></p>', '<p>Hallo <code>~~~</code></p>'],
            'added attribute' => ['<p>Hello</p>', '<p onclick="run()">Привет</p>'],
            'missing translatable attribute' => ['<img alt="Source">', '<img>'],
            'added translatable attribute' => ['<img>', '<img alt="Перевод">'],
            'swapped identified siblings' => [
                '<div><p id="a">One</p><p id="b">Two</p></div>',
                '<div><p id="b">Один</p><p id="a">Два</p></div>',
            ],
            'html5 tree changed' => ['<section><article>Hello</article></section>', '<section>Hello</section>'],
            'comment changed' => ['<p><!--keep-->Hello</p>', '<p><!--change-->Привет</p>'],
            'doctype removed' => [
                '<!DOCTYPE html><html><body><p>Hello</p></body></html>',
                '<html><body><p>Привет</p></body></html>',
            ],
            'doctype identifiers changed' => [
                '<!DOCTYPE html PUBLIC "source" "source.dtd"><html><body><p>Hello</p></body></html>',
                '<!DOCTYPE html PUBLIC "changed" "source.dtd"><html><body><p>Привет</p></body></html>',
            ],
        ];
    }

    public function testPlainTextAndWhitespaceChangesDoNotChangeTheMarkupTree(): void
    {
        $source = '<p>Text <em>one</em> followed by <br>more</p>';
        $output = "<p>Перевод\n<em>один</em><br></p>";
        self::assertTrue((new HtmlStructureValidator($source))->validate($output)->isValid());
    }

    /** @dataProvider clearedTextContainers */
    public function testNonemptyVisibleTextCannotBeCleared(string $source, string $output): void
    {
        $result = (new HtmlStructureValidator($source))->validate($output);

        self::assertFalse($result->isValid());
        self::assertNotEmpty($result->getErrors());
    }

    /** @return array<string,array{string,string}> */
    public static function clearedTextContainers(): array
    {
        return [
            'plain fragment' => ['Visible text', "\u{00a0}\u{2003}"],
            'paragraph' => ['<p>Visible text</p>', '<p> </p>'],
            'division' => ['<div>Visible text</div>', '<div></div>'],
            'heading' => ['<h2>Visible heading</h2>', '<h2></h2>'],
            'list item' => ['<ul><li>Visible item</li></ul>', '<ul><li></li></ul>'],
            'table cell' => [
                '<table><tr><td>Visible cell</td></tr></table>',
                '<table><tr><td></td></tr></table>',
            ],
            'literal zero' => ['<p>0</p>', '<p></p>'],
            'second paragraph hidden by sibling text' => [
                '<div><p>First</p><p>Second</p></div>', '<div><p>Premier</p><p></p></div>',
            ],
            'code cannot hide cleared prose' => [
                '<p>Source <code>literal</code></p>', '<p><code>literal</code></p>',
            ],
            'entities are whitespace' => ['<p>Source</p>', '<p>&nbsp;&#x2003;</p>'],
        ];
    }

    /** @dataProvider clearedAttributes */
    public function testNonemptyTranslatableAttributesCannotBeCleared(string $source, string $output): void
    {
        $result = (new HtmlStructureValidator($source))->validate($output);

        self::assertFalse($result->isValid());
        self::assertNotEmpty($result->getErrors());
    }

    /** @return array<string,array{string,string}> */
    public static function clearedAttributes(): array
    {
        return [
            'alt' => ['<img alt="Source image">', '<img alt="' . "\u{00a0}" . '">'],
            'title' => ['<button title="Source hint">Action</button>', '<button title="">Действие</button>'],
            'aria-label' => [
                '<button aria-label="Close dialog">X</button>',
                '<button aria-label="' . "\u{2003}" . '">X</button>',
            ],
        ];
    }

    public function testInlineTextMayMoveToItsParentAndOpaqueContentIsNotInspected(): void
    {
        $source = '<p>Hello <strong>world</strong></p><pre><code>KEEP 0</code></pre>'
            . '<script>const keep = "opaque";</script><svg><text>KEEP SVG</text></svg>'
            . '<math><mi>KEEP MATH</mi></math>';
        $output = '<p>Привет мир <strong></strong></p><pre><code>KEEP 0</code></pre>'
            . '<script>const keep = "opaque";</script><svg><text></text></svg>'
            . '<math><mi></mi></math>';

        self::assertTrue((new HtmlStructureValidator($source))->validate($output)->isValid());
    }

    public function testEmptySourceContainersAndAttributesMayRemainEmptyOrGainText(): void
    {
        $source = '<div><p></p><img alt="" title="" aria-label=""></div>';
        $output = '<div><p>Добавленный текст</p><img alt="Изображение" title="Подсказка" aria-label="Открыть"></div>';

        self::assertTrue((new HtmlStructureValidator($source))->validate($output)->isValid());
    }

    public function testLiteralBoundaryFencesAlreadyInSourceAreAllowed(): void
    {
        $source = "```html\n<p>Hello</p>\n```";
        $output = "```html\n<p>Привет</p>\n```";
        self::assertTrue((new HtmlStructureValidator($source))->validate($output)->isValid());
    }

    public function testBackticksInsideHtmlTextRemainTranslatable(): void
    {
        $source = '<p>Run <code>```</code> as an example</p>';
        $output = '<p>Пример <code>```</code> для запуска</p>';
        self::assertTrue((new HtmlStructureValidator($source))->validate($output)->isValid());
    }

    public function testFenceEntitiesCompareWithTheirEquivalentLiteralCharacters(): void
    {
        $source = '<p>Hello <code>&grave;&grave;&grave; &#126;&#126;&#126;</code></p>';
        $output = '<p>Hallo <code>``` ~~~</code></p>';
        self::assertTrue((new HtmlStructureValidator($source))->validate($output)->isValid());
    }

    public function testBoundaryFenceWhitespaceRemainsFormattingIndependent(): void
    {
        $source = "  ```html  \r\n<p>Hello</p>\r\n```  ";
        $output = "```html\n<p>Hallo</p>\n```\n";
        self::assertTrue((new HtmlStructureValidator($source))->validate($output)->isValid());
    }

    public function testLibxmlInternalErrorModeIsRestored(): void
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $validator = new HtmlStructureValidator('<section>Hello</section>');
            self::assertTrue($validator->validate('<section>Привет</section>')->isValid());
            self::assertTrue(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    public function testRepeatedValidationRecoversAfterBothKindsOfFailure(): void
    {
        $validator = new HtmlStructureValidator('<p title="Hint">Hello</p>');
        self::assertTrue($validator->validate('<p title="Conseil">Bonjour</p>')->isValid());
        self::assertFalse($validator->validate('<p title="">Bonjour</p>')->isValid());
        self::assertFalse($validator->validate('<p title="Hint" id="added">Hello</p>')->isValid());
        self::assertTrue($validator->validate('<p title="Hinweis">Hallo</p>')->isValid());
    }

    public function testMissingContentReportsFirstTenPathsInTraversalOrder(): void
    {
        $source = '<section title="t" alt="a" aria-label="l"><p title="t">One <span>two</span></p>'
            . '<pre title="t"><p title="t">opaque</p></pre><p alt="a" aria-label="l">Three</p>'
            . '<p>Four</p><p>Five</p></section>';
        $output = '<section title="" alt="" aria-label=""><p title=""> <span></span></p>'
            . '<pre title=""><p title=""></p></pre><p alt="" aria-label=""></p>'
            . '<p></p><p></p></section>';
        $result = (new HtmlStructureValidator($source))->validate($output);

        self::assertSame([
            'Nonempty HTML text or translatable attribute became empty at '
            . '$/div[1]/section[1]/@title, $/div[1]/section[1]/@alt, '
            . '$/div[1]/section[1]/@aria-label, $/div[1]/section[1]/p[1]/@title, '
            . '$/div[1]/section[1]/p[1], $/div[1]/section[1]/pre[2]/@title, '
            . '$/div[1]/section[1]/pre[2]/p[1]/@title, $/div[1]/section[1]/p[3]/@alt, '
            . '$/div[1]/section[1]/p[3]/@aria-label, $/div[1]/section[1]/p[3]',
        ], $result->getErrors());
    }

    public function testMarkupMismatchTakesPrecedenceOverClearedText(): void
    {
        $result = (new HtmlStructureValidator('<p title="Hint">Hello</p>'))
            ->validate('<p title="" id="added"></p>');

        self::assertSame([
            'Markup, non-translatable attributes, and Markdown fences must match the source HTML',
        ], $result->getErrors());
    }

    public function testSameElementSequenceWithDifferentNestingIsRejected(): void
    {
        $source = '<div><section><span>One</span></section><span>Two</span></div>';
        $output = '<div><section><span>Un</span><span>Deux</span></section></div>';
        $result = (new HtmlStructureValidator($source))->validate($output);

        self::assertSame([
            'Markup, non-translatable attributes, and Markdown fences must match the source HTML',
        ], $result->getErrors());
    }
}
