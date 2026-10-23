<?php

declare(strict_types=1);

namespace Bblslug\Translation;

use Bblslug\Filters\FilterManager;
use Bblslug\Validation\PlaceholderValidator;

/** Translate selected HTML string values while preserving all other JSON bytes. */
final class JsonHtmlDocument
{
    /** @var list<array{offset:int,token:string,value:string,prepared:string,filter:FilterManager,html:HtmlTextDocument,ids:array<string,string>}> */
    private array $parts = [];
    /** @var array<string,string> */
    private array $texts = [];
    /** @var array<string,bool> */
    private array $pointers = [];
    /** @var list<array{string,int}> */
    private array $tokens = [];
    private int $cursor = 0;

    /**
     * @param array<int,mixed> $pointers
     * @param array<int,string> $filters
     */
    public function __construct(private string $source, array $pointers, private array $filters = [])
    {
        if ($pointers === []) {
            throw new \InvalidArgumentException('JSON HTML segments require explicit htmlJsonPointers');
        }
        foreach ($pointers as $pointer) {
            if (
                !is_string($pointer)
                || ($pointer !== '' && $pointer[0] !== '/')
                || preg_match('/~(?![01])/', $pointer)
            ) {
                throw new \InvalidArgumentException('Invalid HTML JSON Pointer; use RFC 6901 string syntax');
            }
            $this->pointers[$pointer] = false;
        }
        json_decode($source, false, 512, JSON_THROW_ON_ERROR);
        $pattern = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"|[{}\[\],:]|[^\s{}\[\],:"]+~s
REGEX;
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === false) {
            throw new \InvalidArgumentException('Could not scan source JSON for HTML selections');
        }
        $this->tokens = $matches[0];
        $this->walk('');
        foreach ($this->pointers as $pointer => $found) {
            if (!$found) {
                throw new \InvalidArgumentException(
                    'HTML JSON Pointer does not resolve to a string: '
                    . json_encode($pointer, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                );
            }
        }
        // Token ranges have now been captured; the complete token stream is no longer needed.
        $this->tokens = [];
    }

    public function getJson(): string
    {
        return json_encode(
            (object) $this->texts,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    public function hasTexts(): bool
    {
        return $this->texts !== [];
    }

    public function getPrepared(): string
    {
        $output = '';
        $cursor = 0;
        foreach ($this->parts as $part) {
            $output .= substr($this->source, $cursor, $part['offset'] - $cursor)
                . self::encode($part['prepared'], $part['value'], $part['token']);
            $cursor = $part['offset'] + strlen($part['token']);
        }
        return $output . substr($this->source, $cursor);
    }

    /** @return array<int,array{filter:string,count:int}> */
    public function getFilterStats(): array
    {
        $stats = [];
        foreach ($this->filters as $name) {
            $stats[] = ['filter' => $name, 'count' => 0];
        }
        foreach ($this->parts as $part) {
            foreach ($part['filter']->getStats() as $index => $stat) {
                $stats[$index]['count'] += $stat['count'];
            }
        }
        return $stats;
    }

    /** @return array<int,array{filter:string,map:array<string,string>}> */
    public function getDebugMaps(): array
    {
        $maps = [];
        foreach ($this->parts as $partIndex => $part) {
            foreach ($part['filter']->getDebugMaps() as $filterIndex => $entry) {
                if (!isset($maps[$filterIndex])) {
                    $maps[$filterIndex] = ['filter' => $entry['filter'], 'map' => []];
                }
                foreach ($entry['map'] as $marker => $capture) {
                    $maps[$filterIndex]['map'][$partIndex . ':' . $marker] = $capture;
                }
            }
        }
        return array_values($maps);
    }

    public function restore(string $translatedJson): string
    {
        $translations = SegmentTranslations::decode($translatedJson, array_keys($this->texts));
        $output = '';
        $cursor = 0;
        foreach ($this->parts as $part) {
            $values = [];
            foreach ($part['ids'] as $local => $global) {
                $values[$local] = $translations[$global];
            }
            $html = $part['html']->restore(json_encode((object) $values, JSON_THROW_ON_ERROR));
            $integrity = (new PlaceholderValidator($part['prepared']))->validate($html);
            if (!$integrity->isValid()) {
                throw new \RuntimeException(
                    'HTML JSON placeholder mismatch: ' . implode('; ', $integrity->getErrors())
                );
            }
            $html = $part['filter']->restore($html);
            $output .= substr($this->source, $cursor, $part['offset'] - $cursor)
                . self::encode($html, $part['value'], $part['token']);
            $cursor = $part['offset'] + strlen($part['token']);
        }
        return $output . substr($this->source, $cursor);
    }

    private static function encode(string $value, string $original, string $token): string
    {
        return $value === $original ? $token
            : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @return array{string,int} */
    private function token(): array
    {
        if (!isset($this->tokens[$this->cursor])) {
            throw new \LogicException('Unexpected end of validated JSON token stream');
        }
        return $this->tokens[$this->cursor];
    }

    private static function stringValue(string $token): string
    {
        $value = json_decode($token, true, 512, JSON_THROW_ON_ERROR);
        if (!is_string($value)) {
            throw new \LogicException('Expected a JSON string token');
        }
        return $value;
    }

    private function walk(string $path): void
    {
        [$token, $offset] = $this->token();
        if ($token[0] === '"') {
            if (array_key_exists($path, $this->pointers)) {
                $this->capture($path, $token, $offset);
            }
            $this->cursor++;
            return;
        }
        if (array_key_exists($path, $this->pointers)) {
            throw new \InvalidArgumentException(
                'HTML JSON Pointer does not resolve to a string: '
                . json_encode($path, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
        }
        $this->cursor++;
        if ($token === '{') {
            $seen = [];
            while ($this->token()[0] !== '}') {
                $key = self::stringValue($this->token()[0]);
                if (isset($seen[$key])) {
                    throw new \InvalidArgumentException('Duplicate source JSON keys are ambiguous for HTML selection');
                }
                $seen[$key] = true;
                $this->cursor += 2; // String key and colon (syntax was validated before scanning).
                $this->walk($path . '/' . str_replace(['~', '/'], ['~0', '~1'], $key));
                if ($this->token()[0] !== ',') {
                    break;
                }
                $this->cursor++;
            }
            $this->cursor++; // Closing brace.
        } elseif ($token === '[') {
            $index = 0;
            while ($this->token()[0] !== ']') {
                $this->walk($path . '/' . $index++);
                if ($this->token()[0] !== ',') {
                    break;
                }
                $this->cursor++;
            }
            $this->cursor++; // Closing bracket.
        }
    }

    private function capture(string $path, string $token, int $offset): void
    {
        $value = self::stringValue($token);
        $filter = new FilterManager($this->filters);
        $prepared = $filter->apply($value);
        $html = new HtmlTextDocument($prepared);
        /** @var array<string,string> $localTexts */
        $localTexts = json_decode($html->getJson(), true, 512, JSON_THROW_ON_ERROR);
        $ids = [];
        foreach ($localTexts as $local => $text) {
            $global = 's' . count($this->texts);
            $ids[$local] = $global;
            $this->texts[$global] = $text;
        }
        $this->parts[] = [
            'offset' => $offset, 'token' => $token, 'value' => $value, 'prepared' => $prepared,
            'filter' => $filter, 'html' => $html, 'ids' => $ids,
        ];
        $this->pointers[$path] = true;
    }
}
