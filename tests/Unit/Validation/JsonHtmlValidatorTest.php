<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use Bblslug\Validation\JsonHtmlValidator;
use PHPUnit\Framework\TestCase;

final class JsonHtmlValidatorTest extends TestCase
{
    public function testSelectedMarkupIsCheckedWhileTextAndNaturalLanguageAttributesCanTranslate(): void
    {
        $validator = new JsonHtmlValidator(
            '{"body":"<a href=\"/same\" title=\"Hello\"><em>Hello</em></a>","example":"<b>Code</b>"}',
            ['/body']
        );
        self::assertTrue($validator->validate(
            '{"example":"Anything","body":"<a title=\"Bonjour\" href=\"/same\"><em>Bonjour</em></a>"}'
        )->isValid());
        foreach (
            [
            '<p>Bonjour</div>',
            '<a href="/changed"><em>Bonjour</em></a>',
            '<a href="/same" title="Bonjour"><strong>Bonjour</strong></a>',
            '<a href="/same" title="Bonjour"><em>Bonjour</em></a><img src="/new">',
            ] as $html
        ) {
            self::assertFalse($validator->validate(json_encode(['body' => $html], JSON_THROW_ON_ERROR))->isValid());
        }
    }

    public function testPointersSupportEscapedTokensArraysEmptyKeysAndRootStrings(): void
    {
        foreach (
            [
            ['{"a/b":{"~key":["<p>Hello</p>"]}}', '/a~1b/~0key/0'],
            ['{"":{"body":"<p>Hello</p>"}}', '//body'],
            ['{"":"<p>Hello</p>"}', '/'],
            ['{"~1":"<p>Hello</p>"}', '/~01'],
            ['{"01":"<p>Hello</p>"}', '/01'],
            ['["<p>Hello</p>"]', '/0'],
            ['"<p>Hello</p>"', ''],
            ] as [$source, $pointer]
        ) {
            $validator = new JsonHtmlValidator($source, [$pointer]);
            self::assertTrue($validator->validate(str_replace('Hello', 'Bonjour', $source))->isValid(), $pointer);
            self::assertFalse($validator->validate(str_replace(['<p>', '</p>'], ['<b>', '</b>'], $source))->isValid());
        }
    }

    public function testInvalidOrUnresolvedSourcePointersFailInsteadOfSilentlySkipping(): void
    {
        foreach (
            [
            null, 1, 'body', '#/body', '/~', '/~2', '/missing', '/items/01', '/items/-',
            '/items/999999999999999999999', '/count', '/items', '/body/child',
            ] as $pointer
        ) {
            try {
                new JsonHtmlValidator('{"body":"<p>Hello</p>","items":["<p>Hello</p>"],"count":1}', [$pointer]);
                self::fail('Expected invalid pointer');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringContainsString('Pointer', $exception->getMessage());
            }
        }
    }

    public function testAllSelectedPathsAreCheckedAndMissingOrNonStringOutputsAreRejected(): void
    {
        $validator = new JsonHtmlValidator('{"one":"<p>Hello</p>","two":"<em>World</em>"}', ['/one', '/two']);
        foreach (
            [
            '{"one":"<p>Bonjour</p>","two":"<strong>Monde</strong>"}',
            '{"one":"<p>Bonjour</p>"}',
            '{"one":"<p>Bonjour</p>","two":null}',
            'not JSON',
            ] as $output
        ) {
            self::assertFalse($validator->validate($output)->isValid());
        }
    }

    public function testPointersDoNotImplicitlyTraverseJsonEncodedStrings(): void
    {
        $source = json_encode(['payload' => '{"body":"<p>Hello</p>"}'], JSON_THROW_ON_ERROR);
        $this->expectException(\InvalidArgumentException::class);
        new JsonHtmlValidator($source, ['/payload/body']);
    }
}
