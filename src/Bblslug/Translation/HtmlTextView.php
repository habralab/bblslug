<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/**
 * @internal Decoded HTML text with lazily resolved source byte boundaries.
 * Only the current identity run or entity is retained by the boundary cursor.
 */
final class HtmlTextView
{
    private const ENTITY = '/&(?:#[xX][0-9a-fA-F]+|#[0-9]+|[a-zA-Z][a-zA-Z0-9]+);/u';

    private string $text;
    /** @var \Generator<int,array{int,int,int,int,bool}>|null */
    private ?\Generator $ranges = null;

    public function __construct(private readonly string $raw)
    {
        if (!mb_check_encoding($raw, 'UTF-8')) {
            throw new \InvalidArgumentException('HTML segment transport requires UTF-8 text');
        }
        $decoded = preg_replace_callback(
            self::ENTITY,
            static fn (array $match): string => html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $raw
        );
        if ($decoded === null) {
            throw new \InvalidArgumentException('HTML segment transport requires UTF-8 text');
        }
        $this->text = $decoded;
    }

    public function getText(): string
    {
        return $this->text;
    }

    /** Ordered lookups scan each range once; a backward lookup restarts the cursor. */
    public function sourceOffset(int $offset): ?int
    {
        if ($offset < 0 || $offset > strlen($this->text)) {
            return null;
        }
        if ($offset === 0) {
            return 0;
        }
        if ($offset === strlen($this->text)) {
            return strlen($this->raw);
        }
        $this->ranges ??= self::sourceRanges($this->raw, strlen($this->text));
        if (!$this->ranges->valid() || $offset < $this->ranges->current()[2]) {
            $this->ranges = self::sourceRanges($this->raw, strlen($this->text));
        }
        while ($this->ranges->valid()) {
            [$rawStart, $rawEnd, $decodedStart, $decodedEnd, $identity] = $this->ranges->current();
            if ($offset > $decodedEnd) {
                $this->ranges->next();
                continue;
            }
            if ($offset === $decodedStart) {
                return $rawStart;
            }
            if ($offset === $decodedEnd) {
                return $rawEnd;
            }
            if (!$identity) {
                return null;
            }
            $rawOffset = $rawStart + $offset - $decodedStart;
            // A raw UTF-8 run maps directly, except inside a multibyte code point.
            return (ord($this->raw[$rawOffset]) & 0xC0) === 0x80 ? null : $rawOffset;
        }
        return null;
    }

    /**
     * Static to avoid a view -> generator -> view reference cycle.
     *
     * @return \Generator<int,array{int,int,int,int,bool}> Raw/decoded endpoints and identity flag.
     */
    private static function sourceRanges(string $raw, int $decodedLength): \Generator
    {
        $position = 0;
        $decoded = 0;
        while (($matched = preg_match(self::ENTITY, $raw, $match, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$token, $offset] = $match[0];
            if ($offset > $position) {
                $length = $offset - $position;
                yield [$position, $offset, $decoded, $decoded + $length, true];
                $decoded += $length;
            }
            $end = $offset + strlen($token);
            $length = strlen(html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            // Even an unknown entity candidate is one indivisible source token.
            yield [$offset, $end, $decoded, $decoded + $length, false];
            $decoded += $length;
            $position = $end;
        }
        if ($matched === false) {
            throw new \InvalidArgumentException('HTML segment transport requires UTF-8 text');
        }
        if ($position < strlen($raw)) {
            yield [$position, strlen($raw), $decoded, $decodedLength, true];
        }
    }
}
