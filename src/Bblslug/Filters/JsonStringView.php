<?php

declare(strict_types=1);

namespace Bblslug\Filters;

/** @internal */
final class JsonStringView
{
    private string $text;
    /** @var list<array{decodedStart:int,length:int,sourceStart:int,sourceEnd:int,raw:bool}> */
    private array $segments = [];
    public function __construct(string $token)
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
        $this->buildSegments($token);
    }
    public function getText(): string
    {
        return $this->text;
    }
    public function sourceOffset(int $decodedByteOffset): int
    {
        $decodedLength = strlen($this->text);
        if ($decodedByteOffset < 0 || $decodedByteOffset > $decodedLength) {
            throw new \InvalidArgumentException('Decoded byte offset is outside the string');
        }
        if ($decodedByteOffset === $decodedLength) {
            return count($this->segments) === 0 ? 1 : $this->segments[count($this->segments) - 1]['sourceEnd'];
        }
        $low = 0;
        $high = count($this->segments) - 1;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            $segment = $this->segments[$middle];
            if ($decodedByteOffset < $segment['decodedStart']) {
                $high = $middle - 1;
                continue;
            }
            if ($decodedByteOffset >= $segment['decodedStart'] + $segment['length']) {
                $low = $middle + 1;
                continue;
            }
            if ($segment['raw']) {
                return $segment['sourceStart'] + $decodedByteOffset - $segment['decodedStart'];
            }
            return $segment['sourceStart'];
        }
        throw new \LogicException('Decoded offset map is incomplete');
    }
    private function buildSegments(string $token): void
    {
        $contentEnd = strlen($token) - 1;
        $position = 1;
        $decodedPosition = 0;
        while ($position < $contentEnd) {
            if ($token[$position] !== '\\') {
                $start = $position;
                do {
                    $position++;
                } while ($position < $contentEnd && $token[$position] !== '\\');
                $runLength = $position - $start;
                $this->segments[] = [
                    'decodedStart' => $decodedPosition,
                    'length' => $runLength,
                    'sourceStart' => $start,
                    'sourceEnd' => $position,
                    'raw' => true,
                ];
                $decodedPosition += $runLength;
                continue;
            }
            $start = $position;
            $end = $this->escapeEnd($token, $position, $contentEnd);
            try {
                $escapeValue = json_decode(
                    '"' . substr($token, $start, $end - $start) . '"',
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
            $decodedLength = strlen($escapeValue);
            $this->segments[] = [
                'decodedStart' => $decodedPosition,
                'length' => $decodedLength,
                'sourceStart' => $start,
                'sourceEnd' => $end,
                'raw' => false,
            ];
            $decodedPosition += $decodedLength;
            $position = $end;
        }
    }
    private function escapeEnd(string $token, int $position, int $contentEnd): int
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
