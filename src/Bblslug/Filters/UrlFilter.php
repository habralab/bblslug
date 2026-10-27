<?php

declare(strict_types=1);

namespace Bblslug\Filters;

use Bblslug\Filters\PlaceholderCounter;

class UrlFilter implements DebuggableFilterInterface
{
    private ?FragmentProtection $protection = null;

    public function apply(string $text, PlaceholderCounter $counter): string
    {
        $protection = $this->protection ??= new FragmentProtection();
        $shielded = $protection->shieldLiterals($text, $counter);
        return $protection->capture($shielded, $this->findUrls($shielded), $counter);
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
        return $this->protection?->restore($text) ?? $text;
    }

    /**
     * @return array{filter:string,count:int}
     */
    public function getStats(): array
    {
        return ['filter' => 'url', 'count' => count($this->protection?->getCaptures() ?? [])];
    }

    /** @return array<string,string> Placeholder => captured source slice. */
    public function debugDump(): array
    {
        return $this->protection?->getCaptures() ?? [];
    }
}
