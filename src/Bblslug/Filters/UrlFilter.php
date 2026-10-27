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
        $ranges = JsonStringRanges::find(
            $shielded,
            $this->findLiteralUrls(...),
            'Could not inspect JSON strings for URL fragments'
        );
        return $protection->capture($shielded, $ranges, $counter);
    }

    /** @return array<int,array{int,int}> URL fragments as source byte offsets/lengths. */
    private function findLiteralUrls(string $text): array
    {
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
