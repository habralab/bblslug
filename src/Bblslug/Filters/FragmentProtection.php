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
        // Match decoded JSON strings so digit runs cannot split an adjacent escape.
        $ranges = JsonStringRanges::find(
            $text,
            self::literalRanges(...),
            'Could not protect literal placeholders',
            includeKeys: true,
            mayContainFragments: self::mayContainMarker(...)
        );
        // Decoding may reveal other markers in the same string.
        // Keep the original source-literal trigger so those are not renamed here.
        foreach ($ranges as $index => [$offset, $length]) {
            if (!str_contains(substr($text, $offset, $length), '@@')) {
                unset($ranges[$index]);
            }
        }
        return self::replaceRanges($text, $ranges, $counter, $this->literalMap);
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

    /** @return array<int,array{int,int}> */
    private static function literalRanges(string $text): array
    {
        // Shield complete, overlapping, and partial markers before capturing fragments.
        if (preg_match_all('/(?<![0-9@])[0-9@]*@@[0-9@]*/', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            throw new \RuntimeException('Could not protect literal placeholders');
        }
        $ranges = [];
        foreach ($matches[0] as [$match, $offset]) {
            $ranges[] = [$offset, strlen($match)];
        }
        return $ranges;
    }

    /**
     * @param array<int,array{int,int}> $ranges
     * @param array<string,string> $map
     */
    private static function replaceRanges(
        string $text,
        array $ranges,
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
