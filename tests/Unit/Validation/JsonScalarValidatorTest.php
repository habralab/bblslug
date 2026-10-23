<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use Bblslug\Validation\JsonScalarValidator;
use PHPUnit\Framework\TestCase;

final class JsonScalarValidatorTest extends TestCase
{
    public function testTranslatedStringsAndWhitespaceDoNotAffectScalarComparison(): void
    {
        $source = '{"title":"false 123 null","values":[1,-2,0.123,true,false,null]}';
        $output = "{\n\"title\":\"Перевод 999 true\",\"values\":[1, -2, 0.123, true, false, null]}";
        self::assertTrue((new JsonScalarValidator($source))->validate($output)->isValid());
    }

    public function testNumbersInsideEscapedStringsAreIgnored(): void
    {
        $source = json_encode(['text' => '"123" \\ false true null', 'id' => 7], JSON_THROW_ON_ERROR);
        $output = json_encode(['text' => '"456" \\ null true false', 'id' => 7], JSON_THROW_ON_ERROR);
        self::assertTrue((new JsonScalarValidator($source))->validate($output)->isValid());
    }

    /** @dataProvider scalarChanges */
    public function testChangedScalarTokensAreRejected(string $source, string $output): void
    {
        $result = (new JsonScalarValidator($source))->validate($output);
        self::assertFalse($result->isValid());
        self::assertNotEmpty($result->getErrors());
    }

    /** @return array<string,array{string,string}> */
    public static function scalarChanges(): array
    {
        return [
            'integer' => ['1', '2'],
            'boolean' => ['true', 'false'],
            'null' => ['null', 'false'],
            'negative sign' => ['-1', '1'],
            'big integer' => ['9223372036854775808', '9223372036854775809'],
            'precise decimal' => ['0.12345678901234567891', '0.12345678901234567892'],
            'exponent' => ['1e100', '1e101'],
            'equivalent spelling' => ['1.0', '1e0'],
        ];
    }

    public function testLongStringContentIsSkippedWithoutHittingRegexRecursionLimits(): void
    {
        $longText = str_repeat('text "123" \\ true null ', 10000);
        $source = json_encode(['text' => $longText, 'id' => 42], JSON_THROW_ON_ERROR);
        $output = '{"text":"Перевод","id":42}';
        self::assertTrue((new JsonScalarValidator($source))->validate($output)->isValid());
    }
}
