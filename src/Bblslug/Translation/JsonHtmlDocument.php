<?php

declare(strict_types=1);

namespace Bblslug\Translation;

use Bblslug\Filters\FilterManager;
use Bblslug\JsonPointer;
use Bblslug\Validation\PlaceholderValidator;

/** Translate selected HTML string values while preserving all other JSON bytes. */
final class JsonHtmlDocument
{
    private const TOKEN_PATTERN = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"|[{}\[\],:]|[^\s{}\[\],:"]+~s
REGEX;

    /** @var list<array{offset:int,token:string,value:string,prepared:string,filter:FilterManager,html:HtmlTextDocument,ids:array<string,string>}> */
    private array $parts = [];
    /** @var array<string,string> */
    private array $texts = [];
    /** @var array<string,bool> */
    private array $pointers = [];
    /** @var array{string,int}|null */
    private ?array $currentToken = null;
    private int $position = 0;

    /**
     * @param array<int,mixed> $pointers
     * @param array<int,string> $filters
     */
    public function __construct(private readonly string $source, array $pointers, private array $filters = [])
    {
        if ($pointers === []) {
            throw new \InvalidArgumentException('JSON HTML segments require explicit htmlJsonPointers');
        }
        foreach ($pointers as $pointer) {
            if (!JsonPointer::isValid($pointer)) {
                throw new \InvalidArgumentException('Invalid HTML JSON Pointer; use RFC 6901 string syntax');
            }
            $this->pointers[$pointer] = false;
        }
        json_decode($source, false, 512, JSON_THROW_ON_ERROR);
        $this->walk('');
        foreach ($this->pointers as $pointer => $found) {
            if (!$found) {
                throw new \InvalidArgumentException(
                    'HTML JSON Pointer does not resolve to a string: '
                    . json_encode($pointer, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                );
            }
        }
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
                $stats[$index] = [
                    'filter' => $stat['filter'],
                    'count' => ($stats[$index]['count'] ?? 0) + $stat['count'],
                ];
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
        $expected = array_keys($this->texts);
        return $this->restoreValidated(SegmentTranslations::decode($translatedJson, $expected));
    }

    /**
     * @internal Restore a decoded collection while preserving segment validation.
     * @param array<string|int,mixed> $values
     */
    public function restoreValues(array $values): string
    {
        return $this->restoreValidated(SegmentTranslations::validate($values, $this->expectedIds()));
    }

    /** @return \Generator<int,string> */
    private function expectedIds(): \Generator
    {
        foreach ($this->texts as $id => $_text) {
            yield $id;
        }
    }

    /** @param array<string,string> $translations */
    private function restoreValidated(array $translations): string
    {
        $output = '';
        $cursor = 0;
        foreach ($this->parts as $part) {
            $values = [];
            foreach ($part['ids'] as $local => $global) {
                $values[$local] = $translations[$global];
            }
            $html = $part['html']->restoreValues($values);
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
        if ($this->currentToken === null) {
            $matched = preg_match(self::TOKEN_PATTERN, $this->source, $match, PREG_OFFSET_CAPTURE, $this->position);
            if ($matched === false) {
                throw new \InvalidArgumentException('Could not scan source JSON for HTML selections');
            }
            if ($matched === 0) {
                throw new \LogicException('Unexpected end of validated JSON token stream');
            }
            $this->currentToken = $match[0];
        }
        return $this->currentToken;
    }

    private function advance(int $count = 1): void
    {
        for ($index = 0; $index < $count; ++$index) {
            [$token, $offset] = $this->token();
            $this->position = $offset + strlen($token);
            $this->currentToken = null;
        }
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
            $this->advance();
            return;
        }
        if (array_key_exists($path, $this->pointers)) {
            throw new \InvalidArgumentException(
                'HTML JSON Pointer does not resolve to a string: '
                . json_encode($path, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
        }
        $this->advance();
        if ($token === '{') {
            $seen = [];
            while ($this->token()[0] !== '}') {
                $key = self::stringValue($this->token()[0]);
                if (isset($seen[$key])) {
                    throw new \InvalidArgumentException('Duplicate source JSON keys are ambiguous for HTML selection');
                }
                $seen[$key] = true;
                $this->advance(2); // String key and colon (syntax was validated before scanning).
                $this->walk($path . '/' . JsonPointer::encodeToken($key));
                if ($this->token()[0] !== ',') {
                    break;
                }
                $this->advance();
            }
            $this->advance(); // Closing brace.
        } elseif ($token === '[') {
            $index = 0;
            while ($this->token()[0] !== ']') {
                $this->walk($path . '/' . $index++);
                if ($this->token()[0] !== ',') {
                    break;
                }
                $this->advance();
            }
            $this->advance(); // Closing bracket.
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
