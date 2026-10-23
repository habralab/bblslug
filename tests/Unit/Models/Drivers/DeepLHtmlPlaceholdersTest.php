<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\DeepLHtmlPlaceholders;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeepLHtmlPlaceholdersTest extends TestCase
{
    public function testOnlyNormalTextMarkersAreWrappedAndRoundTripByteForByte(): void
    {
        $source = '<p>@@0@@ and @@0@@</p>'
            . '<a href="https://example.test/@@1@@" data-bblslug-marker="source-0">@@2@@</a>'
            . '<!-- @@3@@ -->'
            . '<![CDATA[@@4@@]]>'
            . '<script>const value = "@@5@@";</script>'
            . '<style>.x::after { content: "@@6@@"; }</style>'
            . '<title>@@7@@</title><textarea>@@8@@</textarea>'
            . '<xmp>@@9@@</xmp><iframe>@@10@@</iframe>'
            . '<noembed>@@11@@</noembed><noframes>@@12@@</noframes>'
            . '<plaintext>@@13@@';

        $placeholders = new DeepLHtmlPlaceholders($source);
        $wrapped = $placeholders->getText();

        self::assertSame($source, $placeholders->restore($wrapped));
        self::assertSame(3, preg_match_all(
            '/<span\s+translate="no"\s+data-bblslug-marker="[^"]+">@@\d+@@<\/span>/i',
            $wrapped,
            $matches
        ));
        self::assertStringContainsString('href="https://example.test/@@1@@"', $wrapped);
        self::assertStringContainsString('<!-- @@3@@ -->', $wrapped);
        self::assertStringContainsString('<script>const value = "@@5@@";</script>', $wrapped);
        self::assertStringContainsString('<title>@@7@@</title>', $wrapped);
    }

    public function testQuotedGreaterThanInAttributeDoesNotEndTagScanEarly(): void
    {
        $source = '<p title="a > b">Before @@0@@ after</p>';
        $placeholders = new DeepLHtmlPlaceholders($source);

        self::assertStringContainsString('title="a > b"', $placeholders->getText());
        self::assertSame($source, $placeholders->restore($placeholders->getText()));
    }

    public function testRepeatedMarkersReceiveDistinctRequestLocalWrapperIds(): void
    {
        $placeholders = new DeepLHtmlPlaceholders('<p>@@0@@ @@0@@</p>');
        $wrapped = $placeholders->getText();

        self::assertSame(2, preg_match_all('/data-bblslug-marker="([^"]+)"/', $wrapped, $matches));
        self::assertCount(2, array_unique($matches[1]));
    }

    public function testProviderMayChangeWrapperSyntaxWithoutChangingMarkerContent(): void
    {
        $placeholders = new DeepLHtmlPlaceholders('<p>@@0@@</p>');
        $wrapped = $placeholders->getText();
        self::assertSame(1, preg_match(
            '/<span\s+translate="no"\s+data-bblslug-marker="([^"]+)">@@0@@<\/span>/i',
            $wrapped,
            $match
        ));

        $id = preg_quote($match[1], '/');
        $provider = preg_replace(
            '/<span\s+translate="no"\s+data-bblslug-marker="' . $id . '">@@0@@<\/span>/i',
            "<SPAN data-bblslug-marker='{$match[1]}' translate='no'>@@0@@</SPAN>",
            $wrapped
        );

        self::assertIsString($provider);
        self::assertSame('<p>@@0@@</p>', $placeholders->restore($provider));
    }

    /** @dataProvider invalidProviderResponses */
    public function testInvalidOrUnexpectedWrappersAreRejected(string $mutation): void
    {
        $placeholders = new DeepLHtmlPlaceholders('<p>@@0@@</p>');
        $wrapped = $placeholders->getText();
        preg_match('/data-bblslug-marker="([^"]+)"/', $wrapped, $match);
        $id = $match[1];
        $provider = match ($mutation) {
            'missing' => '<p>@@0@@</p>',
            'duplicate' => $wrapped . $wrapped,
            'unknown' => str_replace($id, $id . '999', $wrapped),
            'changed' => str_replace('@@0@@', 'changed', $wrapped),
            'nested' => str_replace('@@0@@', '<span data-bblslug-marker="' . $id . '">@@0@@</span>', $wrapped),
            'malformed' => str_replace('</span>', '', $wrapped),
            'leftover' => $wrapped . $id,
        };

        $this->expectException(RuntimeException::class);
        $placeholders->restore($provider);
    }

    /** @return array<string,array{string}> */
    public static function invalidProviderResponses(): array
    {
        return [
            'missing wrapper' => ['missing'],
            'duplicated wrapper' => ['duplicate'],
            'unknown wrapper ID' => ['unknown'],
            'changed content' => ['changed'],
            'nested wrapper' => ['nested'],
            'malformed wrapper' => ['malformed'],
            'leftover nonce' => ['leftover'],
        ];
    }

    public function testIndependentContextsRestoreCorrectlyInReverseOrder(): void
    {
        $first = new DeepLHtmlPlaceholders('<p>@@0@@</p>');
        $second = new DeepLHtmlPlaceholders('<div>@@1@@</div>');
        self::assertSame('<div>@@1@@</div>', $second->restore($second->getText()));
        self::assertSame('<p>@@0@@</p>', $first->restore($first->getText()));
    }

    public function testWrapperNonceIsBoundToTheRequestContext(): void
    {
        $first = new DeepLHtmlPlaceholders('<p>@@0@@</p>');
        $second = new DeepLHtmlPlaceholders('<p>@@0@@</p>');

        $this->expectException(RuntimeException::class);
        $second->restore($first->getText());
    }

    public function testMarkerOnlyInAttributeIsLeftUnchanged(): void
    {
        $source = '<a href="https://example.test/@@0@@" data-marker="@@1@@">Link</a>';
        $placeholders = new DeepLHtmlPlaceholders($source);

        self::assertSame($source, $placeholders->getText());
        self::assertSame($source, $placeholders->restore($source));
    }

    /** @dataProvider rawTextInputs */
    public function testUnclosedRawTextElementLeavesTheRestOfTheSourceAlone(string $source): void
    {
        $placeholders = new DeepLHtmlPlaceholders($source);

        self::assertSame($source, $placeholders->getText());
    }

    /** @return array<string,array{string}> */
    public static function rawTextInputs(): array
    {
        return [
            'script' => ['<script>@@0@@ <p>@@1@@</p>'],
            'style' => ['<style>@@0@@ <p>@@1@@</p>'],
            'textarea' => ['<textarea>@@0@@ <p>@@1@@</p>'],
            'plaintext' => ['<plaintext>@@0@@ <p>@@1@@</p>'],
        ];
    }
}
