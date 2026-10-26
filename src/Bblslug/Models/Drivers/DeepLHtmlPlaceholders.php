<?php

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

/** Give bare text placeholders a non-translatable element in DeepL HTML requests. */
class DeepLHtmlPlaceholders
{
    private string $nonce;
    private string $text;

    /** @var array<string,string> transport ID => original marker */
    private array $markers = [];

    public function __construct(string $source)
    {
        do {
            $this->nonce = bin2hex(random_bytes(12));
        } while (str_contains($source, $this->nonce));

        // Recognize complete tags (including quoted '>'), comments, and raw/RCDATA
        // elements before matching markers. Unclosed raw text consumes the tail.
        $pattern = <<<'REGEX'
~<!--.*?(?:-->|\z)
 |<!\[CDATA\[.*?(?:\]\]>|\z)
 |<plaintext\b(?:[^"'<>]++|"[^"]*"|'[^']*')*+>.*\z
 |<(script|style|title|textarea|xmp|iframe|noembed|noframes)\b(?:[^"'<>]++|"[^"]*"|'[^']*')*+>.*?(?:</\1\s*>|\z)
 |<(?:[^"'<>]++|"[^"]*"|'[^']*')*+>
 |(@@[0-9]+@@)
~six
REGEX;
        $wrapped = preg_replace_callback(
            $pattern,
            /** @param array<int,string> $match */
            function (array $match): string {
                if (($match[2] ?? '') === '') {
                    return $match[0];
                }
                $id = $this->nonce . '-' . count($this->markers);
                $this->markers[$id] = $match[2];
                return '<span translate="no" data-bblslug-marker="' . $id . '">' . $match[2] . '</span>';
            },
            $source
        );
        if ($wrapped === null) {
            throw new \RuntimeException('Could not protect DeepL HTML placeholders');
        }
        $this->text = $wrapped;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function restore(string $translated): string
    {
        $nonce = preg_quote($this->nonce, '~');
        $pattern = '~<span\b(?=[^>]*\bdata-bblslug-marker\s*=\s*(["\'])('
            . $nonce . '-[0-9]+)\1)(?:[^"\'<>]++|"[^"]*"|\'[^\']*\')*+>(.*?)</span\s*>~is';
        $seen = [];
        $restored = preg_replace_callback(
            $pattern,
            /** @param array<int,string> $match */
            function (array $match) use (&$seen): string {
                $id = $match[2];
                if (!isset($this->markers[$id]) || isset($seen[$id]) || $match[3] !== $this->markers[$id]) {
                    throw new \RuntimeException('DeepL HTML placeholder wrapper mismatch');
                }
                $seen[$id] = true;
                return $this->markers[$id];
            },
            $translated
        );
        if ($restored === null || count($seen) !== count($this->markers) || str_contains($restored, $this->nonce)) {
            throw new \RuntimeException('DeepL HTML placeholder wrappers missing or malformed');
        }
        return $restored;
    }
}
