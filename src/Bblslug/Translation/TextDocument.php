<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** Keep line separators, boundary whitespace and protected placeholders local. */
final class TextDocument
{
    /** @var list<array{start:int,length:int,value:string}> */
    private array $spans = [];

    public function __construct(private string $text)
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new \InvalidArgumentException('Text segment transport requires UTF-8 text');
        }
        preg_match_all('/[^\r\n]+/', $text, $lines, PREG_OFFSET_CAPTURE);
        foreach ($lines[0] as [$line, $start]) {
            // Filters restore placeholders after reconstruction, without sending them for translation.
            $chunks = preg_split('/@@[0-9]+@@/', $line, -1, PREG_SPLIT_OFFSET_CAPTURE);
            if ($chunks === false) {
                throw new \RuntimeException('Could not extract text segments');
            }
            foreach ($chunks as [$chunk, $offset]) {
                if (
                    preg_match('/\A\s*/u', $chunk, $leading) !== 1
                    || preg_match('/\s*\z/u', $chunk, $trailing) !== 1
                ) {
                    throw new \RuntimeException('Could not inspect text segment whitespace');
                }
                $length = strlen($chunk) - strlen($leading[0]) - strlen($trailing[0]);
                if ($length > 0) {
                    $from = strlen($leading[0]);
                    $this->spans[] = [
                        'start' => $start + $offset + $from,
                        'length' => $length,
                        'value' => substr($chunk, $from, $length),
                    ];
                }
            }
        }
    }

    public function getJson(): string
    {
        $values = [];
        foreach ($this->spans as $index => $span) {
            $values['s' . $index] = $span['value'];
        }
        return json_encode((object) $values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function hasTexts(): bool
    {
        return $this->spans !== [];
    }

    public function restore(string $translatedJson): string
    {
        $expected = array_map(static fn (int $index): string => 's' . $index, array_keys($this->spans));
        $values = SegmentTranslations::decode($translatedJson, $expected, 'Text');
        $output = '';
        $cursor = 0;
        foreach ($this->spans as $index => $span) {
            if (strpbrk($values['s' . $index], "\r\n") !== false) {
                throw new \RuntimeException('Text segment translations may not introduce line breaks');
            }
            $output .= substr($this->text, $cursor, $span['start'] - $cursor) . $values['s' . $index];
            $cursor = $span['start'] + $span['length'];
        }
        return $output . substr($this->text, $cursor);
    }
}
