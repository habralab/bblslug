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
}
