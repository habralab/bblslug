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

        $output = '';
        $cursor = 0;
        foreach ($this->findUrls($text) as [$offset, $length]) {
            $placeholder = $counter->next();
            $this->map[$placeholder] = substr($text, $offset, $length);
            $output .= substr($text, $cursor, $offset - $cursor) . $placeholder;
            $cursor = $offset + $length;
        }
        return $output . substr($text, $cursor);
    }

    /** @return array<int,array{int,int}> URL fragments as source byte offsets/lengths. */
    private function findUrls(string $text): array
    {
        try {
            json_decode($text, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Literal text/HTML retains support for escaped slash spellings.
            $pattern = <<<'REGEX'
~\b(?:(?:https?|ftp):(?://|\\+/\\+/)|mailto:)(?:\\+/|[^\s"'<>()\\]++)++~i
REGEX;
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
                throw new \RuntimeException('Could not protect URL fragments');
            }
            return array_map(
                /**
                 * @param array{string,int} $match
                 * @return array{int,int}
                 */
                static fn (array $match): array => [$match[1], strlen($match[0])],
                $matches[0]
            );
        }
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
            foreach ($this->findUrls($view->getText()) as [$start, $length]) {
                $rawStart = $view->sourceOffset($start);
                $rawEnd = $view->sourceOffset($start + $length);
                $ranges[] = [$offset + $rawStart, $rawEnd - $rawStart];
            }
        }
        if ($matched === false) {
            throw new \RuntimeException('Could not inspect JSON strings for URL fragments');
        }
        return $ranges;
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
