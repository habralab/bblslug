<?php

declare(strict_types=1);

namespace Bblslug\Filters;

use Bblslug\Filters\FilterInterface;
use Bblslug\Filters\PlaceholderCounter;

class UrlFilter implements FilterInterface
{
    /** @var array<string,string> placeholder => url */
    private array $map = [];

    /** @var array<string,string> generated shield => literal marker-like source text */
    private array $literalMap = [];

    public function apply(string $text, PlaceholderCounter $counter): string
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
        $text = $shielded;

        // Preserve escaped slashes, but leave quote escapes outside the URL.
        $pattern = <<<'REGEX'
~\b(?:(?:https?|ftp):(?://|\\+/\\+/)|mailto:)(?:\\+/|[^\s"'<>()\\]++)++~i
REGEX;
        $replaced = preg_replace_callback(
            $pattern,
            /** @param array<int,string> $m */
            function (array $m) use ($counter): string {
                $ph = $counter->next();
                $this->map[$ph] = $m[0];
                return $ph;
            },
            $text
        );
        if ($replaced === null) {
            throw new \RuntimeException('Could not protect URL fragments');
        }
        return $replaced;
    }

    public function restore(string $text): string
    {
        // Each pass is simultaneous; source markers return after fragment expansion.
        return strtr(strtr($text, $this->map), $this->literalMap);
    }

    /**
     * @return array{filter:string,count:int}
     */
    public function getStats(): array
    {
        return ['filter' => 'url', 'count' => count($this->map)];
    }
}
