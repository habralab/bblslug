<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use Bblslug\JsonPointer;
use PHPUnit\Framework\TestCase;

final class JsonPointerTest extends TestCase
{
    public function testPointerSyntax(): void
    {
        foreach (['', '/', '//', '/foo/0', '/a~1b', '/m~0n', '/%20', "/é\n"] as $pointer) {
            self::assertTrue(JsonPointer::isValid($pointer), var_export($pointer, true));
        }

        $invalid = [null, false, true, 0, 1.5, [], new \stdClass(), 'foo', '#/foo', '~0', '/~', '/~2', '/~~0'];
        foreach ($invalid as $pointer) {
            self::assertFalse(JsonPointer::isValid($pointer), var_export($pointer, true));
        }
    }

    public function testTokenEncodingAndDecoding(): void
    {
        $examples = [
            '' => '',
            'foo' => 'foo',
            'a/b' => 'a~1b',
            'm~n' => 'm~0n',
            '~1' => '~01',
            '%2F' => '%2F',
            '#/foo' => '#~1foo',
            "é\n/tilde~" => "é\n~1tilde~0",
        ];

        foreach ($examples as $token => $encoded) {
            self::assertSame($encoded, JsonPointer::encodeToken($token));
            self::assertSame($token, JsonPointer::decodeToken($encoded));
        }
    }
}
