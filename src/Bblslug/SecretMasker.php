<?php

declare(strict_types=1);

namespace Bblslug;

/** @internal Mask known secret representations without modifying payloads. */
final class SecretMasker
{
    private ?string $pattern = null;

    /** @param array<int,string> $secrets */
    public function __construct(array $secrets)
    {
        $representations = [];
        $flags = [0, JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT];
        foreach ($secrets as $secret) {
            if ($secret === '') {
                continue;
            }
            $variants = [$secret, rawurlencode($secret), urlencode($secret)];
            $pending = array_values(array_unique($variants));
            // A JSON response may wrap a JSON request body or an encoded message.
            for ($depth = 0; $depth <= 2; $depth++) {
                $next = [];
                foreach ($pending as $variant) {
                    if (isset($representations[$variant])) {
                        continue;
                    }
                    $representations[$variant] = '***';
                    if ($depth === 2) {
                        continue;
                    }
                    foreach ($flags as $flag) {
                        $json = json_encode($variant, $flag);
                        if ($json !== false) {
                            $next[] = substr($json, 1, -1);
                        }
                    }
                }
                $pending = array_values(array_unique($next));
            }
        }
        $variants = array_map('strval', array_keys($representations));
        usort($variants, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        $patterns = [];
        foreach ($variants as $variant) {
            $patterns[] = $this->quoteRepresentation($variant);
        }
        if ($patterns !== []) {
            $this->pattern = '~(?:' . implode('|', $patterns) . ')~';
        }
    }

    public function redact(string $text): string
    {
        if ($this->pattern === null) {
            return $text;
        }
        // Longest representations match first; replacement output is never rescanned.
        return preg_replace($this->pattern, '***', $text)
            ?? throw new \RuntimeException('Could not redact secrets');
    }

    private function quoteRepresentation(string $representation): string
    {
        $encodedTokens = <<<'REGEX'
~(%[0-9A-Fa-f]{2}|\\u[0-9A-Fa-f]{4})~
REGEX;
        $parts = preg_split($encodedTokens, $representation, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new \RuntimeException('Could not prepare secret masking');
        }
        $pattern = '';
        foreach ($parts as $index => $part) {
            $quoted = preg_quote($part, '~');
            // Fold hexadecimal escapes only, never the literal characters of a key.
            $pattern .= $index % 2 === 1 ? '(?i:' . $quoted . ')' : $quoted;
        }
        return $pattern;
    }
}
