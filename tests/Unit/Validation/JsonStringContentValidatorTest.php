<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use Bblslug\Validation\JsonStringContentValidator;
use PHPUnit\Framework\TestCase;

final class JsonStringContentValidatorTest extends TestCase
{
    public function testRejectsEmptyNestedStringAndReportsJsonPointerPath(): void
    {
        $source = json_encode([
            'items' => [
                ['title' => 'Keep this title'],
            ],
        ], JSON_THROW_ON_ERROR);
        $output = json_encode([
            'items' => [
                ['title' => ''],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = (new JsonStringContentValidator($source))->validate($output);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('$/items/0/title', implode('; ', $result->getErrors()));
    }

    public function testRejectsUnicodeWhitespaceAndEscapesPointerKey(): void
    {
        $source = json_encode([
            'a/b' => ['tilde~key' => "\u{041f}\u{0440}\u{0438}\u{0432}\u{0435}\u{0442}"],
        ], JSON_THROW_ON_ERROR);
        $output = json_encode([
            'a/b' => ['tilde~key' => "\u{00a0}\u{2003}"],
        ], JSON_THROW_ON_ERROR);

        $result = (new JsonStringContentValidator($source))->validate($output);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('$/a~1b/tilde~0key', implode('; ', $result->getErrors()));
    }

    public function testAllowsEmptySourceStringsAndWhitespaceOnlySourceStringsToRemainEmpty(): void
    {
        $source = json_encode([
            'empty' => '',
            'spaces' => " \t\r\n\u{00a0}",
        ], JSON_THROW_ON_ERROR);
        $output = json_encode([
            'empty' => '',
            'spaces' => "\n\t",
        ], JSON_THROW_ON_ERROR);

        self::assertTrue((new JsonStringContentValidator($source))->validate($output)->isValid());
    }

    public function testAllowsNonemptyTranslationsThatAreShorterAndLiteralZero(): void
    {
        $source = json_encode([
            'title' => 'A very long source sentence',
            'numberAsText' => '00042',
            'html' => '<p>Source text</p>',
        ], JSON_THROW_ON_ERROR);
        $output = json_encode([
            'title' => 'Kurz',
            'numberAsText' => '0',
            'html' => '<p>x</p>',
        ], JSON_THROW_ON_ERROR);

        self::assertTrue((new JsonStringContentValidator($source))->validate($output)->isValid());
    }

    public function testLiteralZeroSourceCannotBecomeEmpty(): void
    {
        self::assertFalse((new JsonStringContentValidator('"0"'))->validate('""')->isValid());
    }

    public function testChecksRootStringAndDoesNotParseEncodedJsonInsideString(): void
    {
        $root = (new JsonStringContentValidator(json_encode('Source', JSON_THROW_ON_ERROR)))
            ->validate(json_encode('', JSON_THROW_ON_ERROR));
        self::assertFalse($root->isValid());
        self::assertStringContainsString('$', implode('; ', $root->getErrors()));

        $source = json_encode([
            'payload' => json_encode(['nested' => 'Source'], JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);
        $output = json_encode([
            'payload' => json_encode(['nested' => ''], JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);

        self::assertTrue((new JsonStringContentValidator($source))->validate($output)->isValid());
    }

    public function testAllowsObjectPropertyReorderingAndRejectsMissingStringBranch(): void
    {
        $source = json_encode([
            'first' => 'Source one',
            'nested' => ['second' => 'Source two'],
        ], JSON_THROW_ON_ERROR);
        $reordered = json_encode([
            'nested' => ['second' => 'Deux'],
            'first' => 'Un',
        ], JSON_THROW_ON_ERROR);
        self::assertTrue((new JsonStringContentValidator($source))->validate($reordered)->isValid());

        $missing = json_encode(['nested' => []], JSON_THROW_ON_ERROR);
        self::assertFalse((new JsonStringContentValidator($source))->validate($missing)->isValid());
    }
}
