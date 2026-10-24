<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\TextDocument;
use PHPUnit\Framework\TestCase;

final class TextDocumentTest extends TestCase
{
    public function testSplitsEveryLinePreservingSeparatorsWhitespaceAndMarkers(): void
    {
        $source = "  First  \r\nSecond\n\tThird\rFourth @@7@@ fifth\r\n";
        $document = new TextDocument($source);

        self::assertTrue($document->hasTexts());
        self::assertSame(
            ['s0' => 'First', 's1' => 'Second', 's2' => 'Third', 's3' => 'Fourth', 's4' => 'fifth'],
            get_object_vars(json_decode($document->getJson(), false, 512, JSON_THROW_ON_ERROR))
        );
        self::assertSame($source, $document->restore($document->getJson()));
        self::assertSame(
            "  Uno  \r\nDos\n\tTres\rCuatro @@7@@ cinco\r\n",
            $document->restore('{"s4":"cinco","s2":"Tres","s0":"Uno","s3":"Cuatro","s1":"Dos"}')
        );
    }

    public function testTextIsLiteralAndUnicodeWhitespaceRemainsInSource(): void
    {
        $source = "\u{2003}Hello & <world>\u{00a0}\n";
        $document = new TextDocument($source);
        self::assertSame(
            ['s0' => 'Hello & <world>'],
            get_object_vars(json_decode($document->getJson(), false, 512, JSON_THROW_ON_ERROR))
        );
        self::assertSame(
            "\u{2003}Hola & <mundo>\u{00a0}\n",
            $document->restore('{"s0":"Hola & <mundo>"}')
        );
    }

    /** @dataProvider invalidTranslations */
    public function testRestoreUsesStrictSegmentTranslations(string $json): void
    {
        $document = new TextDocument("Hello\nWorld");
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/text|segment/i');
        $document->restore($json);
    }

    /** @return list<array{string}> */
    public static function invalidTranslations(): array
    {
        return array_map(static fn (string $json): array => [$json], [
            '{"s0":"a\\nb","s1":"c"}', '{"s0":"a\\rb","s1":"c"}',
            '{}', '{"s0":"a"}', '{"s0":"a","s1":"b","s2":"extra"}',
            '{"s0":null,"s1":"b"}', '{"s0":[],"s1":"b"}', '{"s0":true,"s1":"b"}',
            '{"s0":"","s1":"b"}', '{"s0":" \t","s1":"b"}',
            '{"s0":"new @@9@@","s1":"b"}', '{"s0":"a","s0":"b","s1":"c"}',
            '{"s0":"a","\\u00730":"b","s1":"c"}',
        ]);
    }

    public function testInvalidUtf8IsRejectedBeforeJsonPreparation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TextDocument("valid\n\xC3\x28");
    }

    public function testWhitespaceOnlyMarkersOnlyAndEmptySourcesHaveNoTexts(): void
    {
        foreach ([" \t\r\n\u{2003}", '@@0@@', ''] as $source) {
            $document = new TextDocument($source);
            self::assertFalse($document->hasTexts());
            self::assertSame(
                [],
                get_object_vars(json_decode($document->getJson(), false, 512, JSON_THROW_ON_ERROR))
            );
            self::assertSame($source, $document->restore('{}'));
        }
    }
}
