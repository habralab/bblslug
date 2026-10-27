<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use Bblslug\Console\OptionValues;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OptionValuesTest extends TestCase
{
    /** @dataProvider validIntegers */
    public function testPositiveInteger(array $options, ?int $expected): void
    {
        self::assertSame($expected, OptionValues::positiveInteger($options, 'limit'));
    }

    public static function validIntegers(): array
    {
        return [
            'absent' => [[], null],
            'null' => [['limit' => null], null],
            'one' => [['limit' => '1'], 1],
            'multiple digits' => [['limit' => '123'], 123],
            'maximum' => [['limit' => (string) PHP_INT_MAX], PHP_INT_MAX],
        ];
    }

    /** @dataProvider invalidIntegers */
    public function testRejectsInvalidInteger($value, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/\A' . preg_quote($message, '/') . '\z/');
        OptionValues::positiveInteger(['limit' => $value], 'limit');
    }

    public static function invalidIntegers(): array
    {
        $invalid = '--limit requires a positive integer value.';
        return [
            'empty' => ['', $invalid],
            'zero' => ['0', $invalid],
            'leading zero' => ['01', $invalid],
            'negative' => ['-1', $invalid],
            'plus sign' => ['+1', $invalid],
            'whitespace' => [' 1', $invalid],
            'decimal' => ['1.0', $invalid],
            'integer type' => [1, $invalid],
            'false' => [false, $invalid],
            'empty array' => [[], '--limit may only be specified once.'],
            'repeated' => [['1', '2'], '--limit may only be specified once.'],
            'overflow' => [(string) PHP_INT_MAX . '0', '--limit is too large.'],
            'same length overflow' => [str_repeat('9', strlen((string) PHP_INT_MAX)), '--limit is too large.'],
        ];
    }

    /** @dataProvider validStrings */
    public function testStrings(array $options, array $expected): void
    {
        self::assertSame($expected, OptionValues::strings($options, 'pointer', 'bad pointer'));
    }

    public static function validStrings(): array
    {
        return [
            'absent' => [[], []],
            'null' => [['pointer' => null], []],
            'empty string' => [['pointer' => ''], ['']],
            'scalar' => [['pointer' => '/a'], ['/a']],
            'empty array' => [['pointer' => []], []],
            'order and reindexing' => [['pointer' => [4 => '/a', 1 => '', 'x' => '/b']], ['/a', '', '/b']],
        ];
    }

    /** @dataProvider invalidStrings */
    public function testRejectsNonStringValues($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/\Abad pointer\z/');
        OptionValues::strings(['pointer' => $value], 'pointer', 'bad pointer');
    }

    public static function invalidStrings(): array
    {
        return [
            'false' => [false],
            'zero' => [0],
            'null in array' => [['ok', null]],
            'nested array' => [[['value']]],
        ];
    }

    /** @dataProvider validVariables */
    public function testVariables(array $options, array $expected): void
    {
        self::assertSame($expected, OptionValues::variables($options));
    }

    public static function validVariables(): array
    {
        return [
            'absent' => [[], []],
            'null' => [['variables' => null], []],
            'false' => [['variables' => false], []],
            'repeated ignored' => [['variables' => ['a=1', 'b=2']], []],
            'empty' => [['variables' => ''], []],
            'empty pairs skipped' => [['variables' => ' , , a=1, '], ['a' => '1']],
            'trim and first equals' => [['variables' => ' a = b = c , x = y '], ['a' => 'b = c', 'x' => 'y']],
            'duplicate last wins' => [['variables' => 'a=1,b=2,a=3'], ['a' => '3', 'b' => '2']],
            'empty key and value' => [['variables' => '=value,key='], ['' => 'value', 'key' => '']],
            'values stay strings' => [['variables' => 'zero=0,flag=false'], ['zero' => '0', 'flag' => 'false']],
        ];
    }

    /** @dataProvider invalidVariables */
    public function testRejectsMalformedVariables(string $value, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/\A' . preg_quote($message, '/') . '\z/');
        OptionValues::variables(['variables' => $value]);
    }

    public static function invalidVariables(): array
    {
        return [
            'missing equals' => ['bad', "Invalid --variables format: 'bad'"],
            'trimmed offending pair' => ['a=1,  bad pair  ,b=2', "Invalid --variables format: 'bad pair'"],
            'zero pair' => ['0', "Invalid --variables format: '0'"],
        ];
    }
}
