<?php

declare(strict_types=1);

namespace Bblslug\Filters;

/** @internal Per-filter capture state preserving literal markers and exact source slices. */
final class FragmentProtection
{
    /** @var array<string,string> Placeholder => captured source bytes. */
    private array $map = [];
    /** @var array<string,string> Shield => literal marker-like source bytes. */
    private array $literalMap = [];

    public function shieldLiterals(string $text, PlaceholderCounter $counter): string
    {
        return self::replaceRanges($text, self::sourceLiteralRanges($text), $counter, $this->literalMap);
    }

    /** @return \Generator<int,array{int,int}> */
    private static function sourceLiteralRanges(string $text): \Generator
    {
        $ranges = JsonStringRanges::iterate(
            $text,
            self::literalRanges(...),
            'Could not protect literal placeholders',
            includeKeys: true,
            mayContainFragments: self::mayContainMarker(...)
        );
        foreach ($ranges as [$offset, $length]) {
            // Decoding may reveal other markers; retain the original source-literal trigger.
            if (str_contains(substr($text, $offset, $length), '@@')) {
                yield [$offset, $length];
            }
        }
    }

    /** @param array<int,array{int,int}> $ranges Source byte offsets and lengths, in matching order. */
    public function capture(string $text, array $ranges, PlaceholderCounter $counter): string
    {
        return self::replaceRanges($text, $ranges, $counter, $this->map);
    }

    private static function mayContainMarker(string $text): bool
    {
        return str_contains($text, '@@');
    }

    /** @return \Generator<int,array{int,int}> */
    private static function literalRanges(string $text): \Generator
    {
        // Shield complete, overlapping, and partial markers before capturing fragments.
        $position = 0;
        $pattern = '/(?<![0-9@])[0-9@]*@@[0-9@]*/';
        while (($matched = preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$marker, $offset] = $match[0];
            $length = strlen($marker);
            yield [$offset, $length];
            $position = $offset + $length;
        }
        if ($matched === false) {
            throw new \RuntimeException('Could not protect literal placeholders');
        }
    }

    /**
     * @param iterable<array{int,int}> $ranges
     * @param array<string,string> $map
     */
    private static function replaceRanges(
        string $text,
        iterable $ranges,
        PlaceholderCounter $counter,
        array &$map
    ): string {
        $output = '';
        $cursor = 0;
        foreach ($ranges as [$offset, $length]) {
            $placeholder = $counter->next();
            $map[$placeholder] = substr($text, $offset, $length);
            $output .= substr($text, $cursor, $offset - $cursor) . $placeholder;
            $cursor = $offset + $length;
        }
        return $output . substr($text, $cursor);
    }

    public function restore(string $text): string
    {
        // Expand captured fragments simultaneously before restoring literal markers.
        return strtr(strtr($text, $this->map), $this->literalMap);
    }

    /** @return array<string,string> Placeholder => exact captured source slice. */
    public function getCaptures(): array
    {
        return $this->map;
    }
}
