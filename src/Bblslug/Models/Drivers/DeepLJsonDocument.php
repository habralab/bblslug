<?php

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

/** Keep JSON syntax local while translating only decoded string values. */
class DeepLJsonDocument
{
    private string $source;

    /** @var array<int,string> */
    private array $texts = [];

    /** @var array<int,array{offset:int,length:int,token:string,value:string,nested:?self}> */
    private array $parts = [];

    public function __construct(string $source)
    {
        json_decode($source, false, 512, JSON_THROW_ON_ERROR);
        $this->source = $source;
        $pattern = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"~s
REGEX;
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === false) {
            throw new \RuntimeException('Could not inspect DeepL JSON string values');
        }
        foreach ($matches[0] as [$token, $offset]) {
            $end = $offset + strlen($token);
            // A quoted token followed by a colon is an object key, never a value.
            if (preg_match('/\G\s*:/', $source, $unused, 0, $end) === 1) {
                continue;
            }
            /** @var string $value */
            $value = json_decode($token, true, 512, JSON_THROW_ON_ERROR);
            if (trim($value) === '' || preg_match('/\A(?:@@[0-9]+@@)+\z/', $value) === 1) {
                continue;
            }
            $decoded = json_decode($value, false);
            $nested = json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || $decoded instanceof \stdClass)
                ? new self($value)
                : null;
            $this->parts[] = [
                'offset' => $offset,
                'length' => strlen($token),
                'token' => $token,
                'value' => $value,
                'nested' => $nested,
            ];
            foreach ($nested !== null ? $nested->getTexts() : [$value] as $text) {
                $this->texts[] = $text;
            }
        }
    }

    /** @return array<int,string> */
    public function getTexts(): array
    {
        return $this->texts;
    }

    /** @param array<int,string> $translations */
    public function restore(array $translations): string
    {
        if (!array_is_list($translations) || count($translations) !== count($this->texts)) {
            throw new \RuntimeException('DeepL JSON translation count mismatch');
        }
        $index = 0;
        return $this->replace($translations, $index);
    }

    /** @param array<int,string> $translations */
    private function replace(array $translations, int &$index): string
    {
        $output = '';
        $cursor = 0;
        foreach ($this->parts as $part) {
            $translated = $part['nested'] !== null
                ? $part['nested']->replace($translations, $index)
                : $translations[$index++];
            $replacement = $translated === $part['value']
                ? $part['token']
                : json_encode($translated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $output .= substr($this->source, $cursor, $part['offset'] - $cursor) . $replacement;
            $cursor = $part['offset'] + $part['length'];
        }
        return $output . substr($this->source, $cursor);
    }
}
