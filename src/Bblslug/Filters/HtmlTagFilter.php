<?php

declare(strict_types=1);

namespace Bblslug\Filters;

use Bblslug\Filters\PlaceholderCounter;

class HtmlTagFilter implements DebuggableFilterInterface
{
    private const VOID_TAGS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
        // Obsolete elements that HTML parsers still treat as void.
        'basefont', 'bgsound', 'frame', 'keygen',
    ];

    private string $tag;

    private ?FragmentProtection $protection = null;

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
        $protection = $this->protection ??= new FragmentProtection();
        $shielded = $protection->shieldLiterals($text, $counter);
        $ranges = JsonStringRanges::find(
            $shielded,
            $this->findHtmlBlocks(...),
            'Could not inspect JSON strings for HTML fragments'
        );
        return $protection->capture($shielded, $ranges, $counter);
    }

    /** @return array<int,array{int,int}> Protected HTML blocks as byte offsets/lengths. */
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
 |<(?P<incomplete>{selected_tag})(?=[\s/>]|\z)
~six
REGEX;
        $pattern = str_replace('{selected_tag}', preg_quote($this->tag, '~'), $pattern);
        $depth = 0;
        $start = 0;
        $position = 0;
        $blocks = [];
        while (($matched = preg_match($pattern, $text, $token, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$markup, $offset] = $token[0];
            $position = $offset + strlen($markup);
            if (($token['incomplete'][0] ?? '') !== '') {
                throw new \RuntimeException('Incomplete opening tag for selected HTML filter <' . $this->tag . '>');
            }
            // HTML plaintext consumes the remainder, even when it contains an apparent closing tag.
            if (
                strcasecmp($this->tag, 'plaintext') === 0
                && preg_match('~\A<plaintext(?=[\s/>])~i', $markup) === 1
            ) {
                $blocks[] = [$offset, strlen($markup)];
                continue;
            }
            $rawTag = $token[1][0] ?? '';
            if ($rawTag !== '') {
                if ($depth === 0 && strcasecmp($rawTag, $this->tag) === 0) {
                    if (preg_match('~</' . preg_quote($rawTag, '~') . '\\s*>\z~i', $markup) !== 1) {
                        throw new \RuntimeException('Unclosed selected HTML tag <' . $this->tag . '>');
                    }
                    $blocks[] = [$offset, strlen($markup)];
                }
                continue;
            }
            if (strcasecmp($token[3][0] ?? '', $this->tag) !== 0) {
                continue;
            }
            if (($token[2][0] ?? '') === '') {
                if (in_array(strtolower($this->tag), self::VOID_TAGS, true)) {
                    $blocks[] = [$offset, strlen($markup)];
                    continue;
                }
                if ($depth === 0) {
                    $start = $offset;
                }
                // SVG/MathML roots honor self-closing syntax; ordinary HTML tags do not.
                if ($this->isSelfClosingForeignRoot($markup)) {
                    if ($depth === 0) {
                        $blocks[] = [$offset, strlen($markup)];
                    }
                    continue;
                }
                $depth++;
            } elseif ($depth > 0 && --$depth === 0) {
                $blocks[] = [$start, $offset + strlen($markup) - $start];
            }
        }
        if ($matched === false) {
            throw new \RuntimeException('Could not protect HTML tag fragments');
        }
        if ($depth > 0 && !in_array(strtolower($this->tag), self::VOID_TAGS, true)) {
            throw new \RuntimeException('Unclosed selected HTML tag <' . $this->tag . '>');
        }
        return $blocks;
    }

    private function isSelfClosingForeignRoot(string $markup): bool
    {
        if (!in_array(strtolower($this->tag), ['svg', 'math'], true)) {
            return false;
        }
        // A trailing slash in an unquoted attribute value belongs to that value.
        $pattern = <<<'REGEX'
~\A<(?:svg|math)(?:[\t\n\f\r ]+[^\s/="'<>]++
    (?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'=<>`]++))?)*\s*/>\z~ix
REGEX;
        return preg_match($pattern, $markup) === 1;
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
        return ['filter' => "html_{$this->tag}", 'count' => count($this->protection?->getCaptures() ?? [])];
    }

    /** @return array<string,string> Placeholder => captured source slice. */
    public function debugDump(): array
    {
        return $this->protection?->getCaptures() ?? [];
    }
}
