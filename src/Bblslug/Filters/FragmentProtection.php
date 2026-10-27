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
        // Shield complete, overlapping, and partial markers before capturing fragments.
        $shielded = preg_replace_callback(
            '/(?<![0-9@])[0-9@]*@@[0-9@]*/',
            /** @param array<int,string> $match */
            function (array $match) use ($counter): string {
                $placeholder = $counter->next();
                $this->literalMap[$placeholder] = $match[0];
                return $placeholder;
            },
            $text
        );
        if ($shielded === null) {
            throw new \RuntimeException('Could not protect literal placeholders');
        }
        return $shielded;
    }

    /** @param array<int,array{int,int}> $ranges Source byte offsets and lengths, in matching order. */
    public function capture(string $text, array $ranges, PlaceholderCounter $counter): string
    {
        $output = '';
        $cursor = 0;
        foreach ($ranges as [$offset, $length]) {
            $placeholder = $counter->next();
            $this->map[$placeholder] = substr($text, $offset, $length);
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
