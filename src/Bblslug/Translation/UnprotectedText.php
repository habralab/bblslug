<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** @internal Yield byte ranges between numeric placeholders for segment extraction. */
final class UnprotectedText
{
    /** @return iterable<int,array{int,int}> Start and end offsets, including empty gaps. */
    public static function ranges(string $text): iterable
    {
        if (!str_contains($text, '@@')) {
            return [[0, strlen($text)]];
        }
        return self::scan($text);
    }

    /** @return \Generator<int,array{int,int}> */
    private static function scan(string $text): \Generator
    {
        $cursor = 0;
        while (($matched = preg_match('/(?=@@[0-9]+@@)/', $text, $match, PREG_OFFSET_CAPTURE, $cursor)) === 1) {
            // Match only the start; a large digit run must not be copied into captures.
            $offset = $match[0][1];
            $end = strpos($text, '@@', $offset + 2);
            if ($end === false) {
                throw new \RuntimeException('Could not extract text segments');
            }
            yield [$cursor, $offset];
            $cursor = $end + 2;
        }
        if ($matched === false) {
            throw new \RuntimeException('Could not extract text segments');
        }
        yield [$cursor, strlen($text)];
    }
}
