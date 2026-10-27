<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use Bblslug\Console\RawArguments;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RawArgumentsTest extends TestCase
{
    private const LONG_OPTIONS = [
        'source:', 'translated:', 'validate-html-json-pointer:', 'html-json-pointer:',
        'html-transport:', 'text-transport:', 'html-segment-batch-size:', 'text-segment-batch-size:',
        'segment-batch-max-bytes:', 'operation-timeout:', 'checkpoint:', 'segment-abbreviation:',
    ];

    /** @dataProvider acceptedArguments */
    public function testAcceptsArguments(array $argv): void
    {
        RawArguments::validate($argv, self::LONG_OPTIONS);
        self::assertTrue(true);
    }

    public static function acceptedArguments(): array
    {
        return [
            'root pointers as separate tokens' => [
                ['cli', '--validate-html-json-pointer', '', '--html-json-pointer', ''],
            ],
            'stop at terminator' => [['cli', '--', '--translated=', '--translated=']],
            'required value resembling flag is skipped' => [['cli', '--source', '--translated=', '--translated=file']],
            'non-string tokens are skipped' => [['cli', null, 17, ['--translated='], '--translated=file']],
            'empty HTML transport separate token' => [['cli', '--html-transport', '']],
            'empty HTML batch size separate token' => [['cli', '--html-segment-batch-size', '']],
            'nonempty inline and separate values' => [['cli', '--translated=file', '--text-transport', 'segments']],
            'pointer may repeat' => [['cli', '--html-json-pointer=/a', '--html-json-pointer', '/b']],
        ];
    }

    /** @dataProvider rejectedArguments */
    public function testRejectsArguments(array $argv, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/\\A' . preg_quote($message, '/') . '\\z/');
        RawArguments::validate($argv, self::LONG_OPTIONS);
    }

    public static function rejectedArguments(): array
    {
        return [
            'validate pointer inline root' => [['cli', '--validate-html-json-pointer='],
                'Empty --validate-html-json-pointer value must be passed as a separate empty argument.'],
            'selection pointer inline root' => [['cli', '--html-json-pointer='],
                'Empty --html-json-pointer value must be passed as a separate empty argument.'],
            'pointer missing' => [['cli', '--html-json-pointer', '--translated=file'],
                '--html-json-pointer requires a value.'],
            'translated empty inline' => [['cli', '--translated='], '--translated requires a non-empty path.'],
            'translated empty separate' => [['cli', '--translated', ''], '--translated requires a path.'],
            'translated missing' => [['cli', '--translated'], '--translated requires a path.'],
            'text transport empty inline' => [['cli', '--text-transport='],
                '--text-transport requires document or segments.'],
            'text transport empty separate' => [['cli', '--text-transport', ''],
                '--text-transport requires a value.'],
            'HTML transport empty inline' => [['cli', '--html-transport='],
                '--html-transport requires document or segments.'],
            'HTML transport missing' => [['cli', '--html-transport'], '--html-transport requires a value.'],
            'HTML batch empty inline' => [['cli', '--html-segment-batch-size='],
                '--html-segment-batch-size requires a positive integer value.'],
            'HTML batch missing' => [['cli', '--html-segment-batch-size'],
                '--html-segment-batch-size requires a value.'],
            'text batch empty inline' => [['cli', '--text-segment-batch-size='],
                '--text-segment-batch-size requires a positive integer value.'],
            'text batch empty separate' => [['cli', '--text-segment-batch-size', ''],
                '--text-segment-batch-size requires a value.'],
            'translated repeated' => [['cli', '--translated=a', '--translated', 'b'],
                '--translated may only be specified once.'],
            'HTML batch repeated' => [['cli', '--html-segment-batch-size=1', '--html-segment-batch-size=2'],
                '--html-segment-batch-size may only be specified once.'],
            'text transport repeated' => [['cli', '--text-transport=a', '--text-transport=b'],
                '--text-transport may only be specified once.'],
            'text batch repeated' => [['cli', '--text-segment-batch-size=1', '--text-segment-batch-size=2'],
                '--text-segment-batch-size may only be specified once.'],
            'byte limit repeated' => [['cli', '--segment-batch-max-bytes=1', '--segment-batch-max-bytes=2'],
                '--segment-batch-max-bytes may only be specified once.'],
            'timeout repeated' => [['cli', '--operation-timeout=1', '--operation-timeout=2'],
                '--operation-timeout may only be specified once.'],
            'checkpoint repeated' => [['cli', '--checkpoint=a', '--checkpoint=b'],
                '--checkpoint may only be specified once.'],
            'immediate error precedes repeat checks' => [['cli', '--translated=a', '--translated=b', '--checkpoint='],
                '--checkpoint requires a non-empty path.'],
            'repeat checks have fixed priority' => [['cli', '--checkpoint=a', '--checkpoint=b',
                '--translated=a', '--translated=b'], '--translated may only be specified once.'],
        ];
    }
}
