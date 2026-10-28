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
        $ranges = JsonStringRanges::iterate(
            $shielded,
            $this->findLiteralUrls(...),
            'Could not inspect JSON strings for URL fragments'
        );
        return $protection->capture($shielded, $ranges, $counter);
    }

    /** @return \Generator<int,array{int,int}> URL fragments as source byte offsets/lengths. */
    private function findLiteralUrls(string $text): \Generator
    {
        // Literal text/HTML retains support for escaped slash spellings.
        $pattern = <<<'REGEX'
~\b(?:(?:https?|ftp):(?://|\\+/\\+/)|mailto:)(?:\\+/|[^\s"'<>()\\]++)++~i
REGEX;
        $position = 0;
        while (($matched = preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$url, $offset] = $match[0];
            $length = strlen($url);
            yield [$offset, $length];
            $position = $offset + $length;
        }
        if ($matched === false) {
            throw new \RuntimeException('Could not protect URL fragments');
        }
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
