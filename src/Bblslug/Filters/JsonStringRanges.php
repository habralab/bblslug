<?php

declare(strict_types=1);

namespace Bblslug\Filters;

/** @internal Find fragments in JSON string values while retaining source byte ranges. */
final class JsonStringRanges
{
    /**
     * @param callable(string):array<int,array{int,int}> $findFragments Plain-text fragment matcher.
     * @return array<int,array{int,int}> Source byte offsets and lengths.
     */
    public static function find(string $text, callable $findFragments, string $errorMessage): array
    {
        try {
            json_decode($text, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $findFragments($text);
        }
        // Inspect decoded values, skip object keys, and map matches back to exact source bytes.
        $pattern = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"~s
REGEX;
        $position = 0;
        $ranges = [];
        while (($matched = preg_match($pattern, $text, $token, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$source, $offset] = $token[0];
            $position = $offset + strlen($source);
            if (preg_match('/\G\s*:/', $text, $unused, 0, $position) === 1) {
                continue;
            }
            $view = new JsonStringView($source);
            foreach (self::find($view->getText(), $findFragments, $errorMessage) as [$start, $length]) {
                $rawStart = $view->sourceOffset($start);
                $rawEnd = $view->sourceOffset($start + $length);
                $ranges[] = [$offset + $rawStart, $rawEnd - $rawStart];
            }
        }
        if ($matched === false) {
            throw new \RuntimeException($errorMessage);
        }
        return $ranges;
    }
}
