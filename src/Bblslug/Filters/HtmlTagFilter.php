<?php

declare(strict_types=1);

namespace Bblslug\Filters;

use Bblslug\Filters\FilterInterface;
use Bblslug\Filters\PlaceholderCounter;

class HtmlTagFilter implements FilterInterface
{
    private string $tag;

    /** @var array<string,string> placeholder => original */
    private array $map = [];

    /** @var array<string,string> generated shield => literal marker-like source text */
    private array $literalMap = [];

    public function __construct(string $tag)
    {
        $pattern = <<<'REGEX'
~\A[a-z][^\s/<>"'\x00-\x1f\x7f]*\z~i
REGEX;
        if (preg_match($pattern, $tag) !== 1) {
            throw new \InvalidArgumentException('Invalid HTML tag name for filter');
        }
        $this->tag = $tag;
    }

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
        foreach ($this->findBlocks($text) as [$offset, $length]) {
            $placeholder = $counter->next();
            $this->map[$placeholder] = substr($text, $offset, $length);
            $output .= substr($text, $cursor, $offset - $cursor) . $placeholder;
            $cursor = $offset + $length;
        }
        return $output . substr($text, $cursor);
    }

    /** @return array<int,array{int,int}> Protected fragments as source byte offsets/lengths. */
    private function findBlocks(string $text): array
    {
        try {
            json_decode($text, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->findHtmlBlocks($text);
        }
        // Inspect JSON string values without rewriting keys, scalar tokens or whitespace.
        $pattern = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"~s
REGEX;
        $position = 0;
        $blocks = [];
        while (($matched = preg_match($pattern, $text, $token, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$source, $offset] = $token[0];
            $position = $offset + strlen($source);
            if (preg_match('/\G\s*:/', $text, $unused, 0, $position) === 1) {
                continue;
            }
            $view = new JsonStringView($source);
            foreach ($this->findBlocks($view->getText()) as [$start, $length]) {
                $rawStart = $view->sourceOffset($start);
                $rawEnd = $view->sourceOffset($start + $length);
                $blocks[] = [$offset + $rawStart, $rawEnd - $rawStart];
            }
        }
        if ($matched === false) {
            throw new \RuntimeException('Could not inspect JSON strings for HTML fragments');
        }
        return $blocks;
    }

    /** @return array<int,array{int,int}> Complete outermost HTML blocks as byte offsets/lengths. */
    private function findHtmlBlocks(string $text): array
    {
        // Opaque contexts come first: their apparent tags must not change depth.
        // Token offsets let restoration preserve the user's original HTML bytes.
        $pattern = <<<'REGEX'
~<!--.*?(?:-->|\z)
 |<!\[CDATA\[.*?(?:\]\]>|\z)
 |<plaintext(?=[\s/>])(?:[^"'<>]++|"[^"]*"|'[^']*')*+>.*\z
 |<(script|style|title|textarea|xmp|iframe|noembed|noframes)(?=[\s/>])
    (?:[^"'<>]++|"[^"]*"|'[^']*')*+>.*?(?:</\1\s*>|\z)
 |<(/?)([a-z][^\s/<>"']*)(?=[\s/>])(?:[^"'<>]++|"[^"]*"|'[^']*')*+>
 |<!(?:[^"'<>]++|"[^"]*"|'[^']*')*+>
~six
REGEX;
        $depth = 0;
        $start = 0;
        $position = 0;
        $blocks = [];
        while (($matched = preg_match($pattern, $text, $token, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$markup, $offset] = $token[0];
            $position = $offset + strlen($markup);
            $rawTag = $token[1][0] ?? '';
            if ($rawTag !== '') {
                if (
                    $depth === 0 && strcasecmp($rawTag, $this->tag) === 0
                    && preg_match('~</' . preg_quote($rawTag, '~') . '\\s*>\z~i', $markup) === 1
                ) {
                    $blocks[] = [$offset, strlen($markup)];
                }
                continue;
            }
            if (strcasecmp($token[3][0] ?? '', $this->tag) !== 0) {
                continue;
            }
            if (($token[2][0] ?? '') === '') {
                if ($depth === 0) {
                    $start = $offset;
                }
                $depth++;
            } elseif ($depth > 0 && --$depth === 0) {
                $blocks[] = [$start, $offset + strlen($markup) - $start];
            }
        }
        if ($matched === false) {
            throw new \RuntimeException('Could not protect HTML tag fragments');
        }
        return $blocks;
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
        return ['filter' => "html_{$this->tag}", 'count' => count($this->map)];
    }
}
