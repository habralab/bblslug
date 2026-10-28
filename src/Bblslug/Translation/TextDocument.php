<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** Keep line separators, boundary whitespace and protected placeholders local. */
final class TextDocument
{
    /** @var list<TextSpan> */
    private array $spans = [];

    public function __construct(private readonly string $text)
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new \InvalidArgumentException('Text segment transport requires UTF-8 text');
        }
        $position = 0;
        while (($matched = preg_match('/[^\r\n]+/', $text, $match, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$line, $start] = $match[0];
            $position = $start + strlen($line);
            // Filters restore placeholders after reconstruction, without sending them for translation.
            foreach (UnprotectedText::ranges($line) as [$offset, $end]) {
                $chunk = substr($line, $offset, $end - $offset);
                if (
                    preg_match('/\A\s*/u', $chunk, $leading) !== 1
                    || preg_match('/\s*\z/u', $chunk, $trailing) !== 1
                ) {
                    throw new \RuntimeException('Could not inspect text segment whitespace');
                }
                $length = strlen($chunk) - strlen($leading[0]) - strlen($trailing[0]);
                if ($length > 0) {
                    $from = strlen($leading[0]);
                    $this->spans[] = new TextSpan($start + $offset + $from, $length);
                }
            }
        }
        if ($matched === false) {
            throw new \RuntimeException('Could not extract text segments');
        }
    }

    public function getJson(): string
    {
        $values = [];
        foreach ($this->spans as $index => $span) {
            $values['s' . $index] = substr($this->text, $span->start, $span->length);
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
        $values = SegmentTranslations::decode($translatedJson, $expected, 'Text', false);
        $output = '';
        $cursor = 0;
        foreach ($this->spans as $index => $span) {
            $output .= substr($this->text, $cursor, $span->start - $cursor) . $values['s' . $index];
            $cursor = $span->start + $span->length;
        }
        return $output . substr($this->text, $cursor);
    }
}
