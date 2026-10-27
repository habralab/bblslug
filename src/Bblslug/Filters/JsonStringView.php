<?php

declare(strict_types=1);

namespace Bblslug\Filters;

/**
 * @internal Decoded JSON string with lazily resolved source byte offsets.
 * The cursor retains one raw run or escape, including a complete surrogate pair.
 */
final class JsonStringView
{
    private string $text;
    /** @var \Generator<int,array{int,int,int,bool}>|null */
    private ?\Generator $ranges = null;

    public function __construct(private readonly string $token)
    {
        $length = strlen($token);
        if ($length < 2 || $token[0] !== '"' || $token[$length - 1] !== '"') {
            throw new \InvalidArgumentException('JSON string token must include its surrounding quotes');
        }
        try {
            $decoded = json_decode($token, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(
                'Invalid JSON string token: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
        if (!is_string($decoded)) {
            throw new \InvalidArgumentException('JSON token is not a string');
        }
        $this->text = $decoded;
    }

    public function getText(): string
    {
        return $this->text;
    }

    /** Ordered lookups scan each raw run or escape once; backward lookups restart. */
    public function sourceOffset(int $decodedByteOffset): int
    {
        $decodedLength = strlen($this->text);
        if ($decodedByteOffset < 0 || $decodedByteOffset > $decodedLength) {
            throw new \InvalidArgumentException('Decoded byte offset is outside the string');
        }
        if ($decodedByteOffset === $decodedLength) {
            return strlen($this->token) - 1;
        }
        if ($decodedByteOffset === 0) {
            return 1;
        }
        $this->ranges ??= self::sourceRanges($this->token);
        if (!$this->ranges->valid() || $decodedByteOffset < $this->ranges->current()[1]) {
            $this->ranges = self::sourceRanges($this->token);
        }
        while ($this->ranges->valid()) {
            [$rawStart, $decodedStart, $decodedEnd, $identity] = $this->ranges->current();
            if ($decodedByteOffset >= $decodedEnd) {
                $this->ranges->next();
                continue;
            }
            // Unlike HTML entities, every byte inside a decoded JSON escape maps to its start.
            return $identity ? $rawStart + $decodedByteOffset - $decodedStart : $rawStart;
        }
        throw new \LogicException('Decoded offset map is incomplete');
    }

    /**
     * Static to avoid a view -> generator -> view reference cycle.
     *
     * @return \Generator<int,array{int,int,int,bool}> Source start, decoded interval and identity flag.
     */
    private static function sourceRanges(string $token): \Generator
    {
        $contentEnd = strlen($token) - 1;
        $position = 1;
        $decodedPosition = 0;
        while ($position < $contentEnd) {
            $escape = strpos($token, '\\', $position);
            $rawEnd = $escape === false ? $contentEnd : $escape;
            if ($rawEnd > $position) {
                $length = $rawEnd - $position;
                yield [$position, $decodedPosition, $decodedPosition + $length, true];
                $decodedPosition += $length;
                $position = $rawEnd;
            }
            if ($position === $contentEnd) {
                return;
            }
            $end = self::escapeEnd($token, $position, $contentEnd);
            try {
                $escapeValue = json_decode(
                    '"' . substr($token, $position, $end - $position) . '"',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $exception) {
                throw new \LogicException('Could not decode JSON escape sequence', 0, $exception);
            }
            if (!is_string($escapeValue)) {
                throw new \LogicException('JSON escape sequence did not decode to a string');
            }
            $length = strlen($escapeValue);
            yield [$position, $decodedPosition, $decodedPosition + $length, false];
            $decodedPosition += $length;
            $position = $end;
        }
    }

    private static function escapeEnd(string $token, int $position, int $contentEnd): int
    {
        $escapeCode = $position + 1;
        if ($escapeCode >= $contentEnd) {
            throw new \LogicException('Unexpected end while scanning JSON escape sequence');
        }
        if ($token[$escapeCode] !== 'u') {
            return $escapeCode + 1;
        }
        $end = $escapeCode + 5;
        if ($end > $contentEnd) {
            throw new \LogicException('Incomplete JSON Unicode escape sequence');
        }
        if ($end + 6 <= $contentEnd && substr($token, $end, 2) === '\\u') {
            $first = hexdec(substr($token, $escapeCode + 1, 4));
            $second = hexdec(substr($token, $end + 2, 4));
            if (
                $first >= 0xD800 && $first <= 0xDBFF
                && $second >= 0xDC00 && $second <= 0xDFFF
            ) {
                $end += 6;
            }
        }
        return $end;
    }
}
