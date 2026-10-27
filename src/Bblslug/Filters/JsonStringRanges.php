<?php

declare(strict_types=1);

namespace Bblslug\Filters;

/** @internal Find fragments in JSON string values while retaining source byte ranges. */
final class JsonStringRanges
{
    /**
     * @param callable(string):array<int,array{int,int}> $findFragments Plain-text fragment matcher.
     * @param (callable(string):bool)|null $mayContainFragments Conservative pretest for raw or decoded strings.
     * @return array<int,array{int,int}> Source byte offsets and lengths.
     */
    public static function find(
        string $text,
        callable $findFragments,
        string $errorMessage,
        bool $includeKeys = false,
        ?callable $mayContainFragments = null
    ): array {
        if ($mayContainFragments !== null && !$mayContainFragments($text)) {
            return [];
        }
        try {
            json_decode($text, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $findFragments($text);
        }
        // Fragment filters skip keys; literal-marker shielding must inspect them too.
        $pattern = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"~s
REGEX;
        $position = 0;
        $ranges = [];
        while (($matched = preg_match($pattern, $text, $token, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$source, $offset] = $token[0];
            $position = $offset + strlen($source);
            if (!$includeKeys && preg_match('/\G\s*:/', $text, $unused, 0, $position) === 1) {
                continue;
            }
            if ($mayContainFragments !== null && !$mayContainFragments($source)) {
                continue;
            }
            $view = new JsonStringView($source);
            $matches = self::find($view->getText(), $findFragments, $errorMessage, $includeKeys, $mayContainFragments);
            foreach ($matches as [$start, $length]) {
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
