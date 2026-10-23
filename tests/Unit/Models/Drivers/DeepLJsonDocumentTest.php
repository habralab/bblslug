<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\DeepLJsonDocument;
use PHPUnit\Framework\TestCase;

final class DeepLJsonDocumentTest extends TestCase
{
    public function testKeysScalarsAndFormattingStayLocalAndUnchanged(): void
    {
        $source = "{\n  \"\\u0061\": \"Hello\", \"id\": 9223372036854775809,"
            . ' "decimal":0.12345678901234567891, "exponent":1e+20, "on":true, "empty":null}';
        $doc = new DeepLJsonDocument($source);
        self::assertSame(['Hello'], $doc->getTexts());
        $translated = 'Привет "quoted" \\ path' . "\nnext";
        $output = $doc->restore([$translated]);
        self::assertSame($translated, json_decode($output, true, 512, JSON_THROW_ON_ERROR)['a']);
        self::assertSame(
            str_replace('"Hello"', json_encode($translated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $source),
            $output
        );
    }

    public function testEmbeddedJsonContainersAreReconstructedRecursively(): void
    {
        $source = json_encode([
            'payload' => '{"id":9223372036854775809,"label":"Hello","child":"[\"World\",true]"}',
        ], JSON_THROW_ON_ERROR);
        $doc = new DeepLJsonDocument($source);
        self::assertSame(['Hello', 'World'], $doc->getTexts());
        $outer = json_decode($doc->restore(['Hallo', 'Welt']), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('{"id":9223372036854775809,"label":"Hallo","child":"[\"Welt\",true]"}', $outer['payload']);
    }

    public function testRootStringsWhitespaceAndLiteralFormerTagsRoundTrip(): void
    {
        foreach (['"Hello"', '[""," ","<jlc/>","true"]', '{"a":"\\u0048ello"}', '[]', '{}', 'null', '123'] as $source) {
            $doc = new DeepLJsonDocument($source);
            self::assertSame($source, $doc->restore($doc->getTexts()));
        }
    }

    public function testMissingAndExtraTranslationsAreRejected(): void
    {
        $doc = new DeepLJsonDocument('["One","Two"]');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('count mismatch');
        $doc->restore(['Eins', 'Zwei', 'Drei']);
    }

    public function testLongEscapedStringsCanBeInspectedWithoutRegexRecursionFailure(): void
    {
        $value = str_repeat('long "123" \\ content ', 10000);
        $source = json_encode(['text' => $value], JSON_THROW_ON_ERROR);
        $doc = new DeepLJsonDocument($source);
        self::assertSame([$value], $doc->getTexts());
        self::assertSame($source, $doc->restore([$value]));
    }
}
