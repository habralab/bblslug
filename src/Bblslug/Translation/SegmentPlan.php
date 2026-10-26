<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** Plan byte- and count-bounded packets of a flat segment document. */
final class SegmentPlan
{
    /** @var array<string,string> */
    private array $originals;

    /** @var list<array{id:string, original:string, text:string, separator:string}> */
    private array $chunks = [];

    /** @var list<string> */
    private array $payloads = [];

    /** @var array<string,string> */
    private array $trailing = [];

    public function __construct(string $payload, ?int $maxSegments, int $maxBytes)
    {
        if ($maxSegments !== null && $maxSegments < 1) {
            throw new \InvalidArgumentException('Segment count cap must be positive');
        }
        if ($maxBytes < 1) {
            throw new \InvalidArgumentException('Segment byte cap must be positive');
        }

        try {
            $decoded = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid segment JSON', 0, $exception);
        }
        if (!$decoded instanceof \stdClass) {
            throw new \InvalidArgumentException('Segment JSON must be an object');
        }

        $keys = array_keys(get_object_vars($decoded));
        foreach ($keys as $key) {
            if (preg_match('/\As\d+\z/', $key) !== 1) {
                throw new \InvalidArgumentException('Segment JSON contains an invalid segment id');
            }
            $number = substr($key, 1);
            if (
                strlen($number) > strlen((string) PHP_INT_MAX)
                || (strlen($number) === strlen((string) PHP_INT_MAX) && strcmp($number, (string) PHP_INT_MAX) > 0)
            ) {
                throw new \InvalidArgumentException('Segment JSON contains an out-of-range segment id');
            }
        }
        try {
            /** @var array<string,string> $values */
            $values = SegmentTranslations::decode($payload, $keys);
        } catch (\RuntimeException $exception) {
            throw new \InvalidArgumentException('Invalid segment source payload', 0, $exception);
        }
        $this->originals = $values;
        if ($values === []) {
            return;
        }

        $used = [];
        $maxExisting = -1;
        foreach ($values as $id => $_value) {
            $used[$id] = true;
            $number = (int) substr($id, 1);
            $maxExisting = max($maxExisting, $number);
        }
        $nextId = $maxExisting === PHP_INT_MAX ? PHP_INT_MAX : $maxExisting + 1;

        foreach ($values as $original => $value) {
            $matches = [];
            preg_match_all('/\S+/u', $value, $matches, PREG_OFFSET_CAPTURE);
            if ($matches[0] === []) {
                throw new \InvalidArgumentException('Segment values must contain non-whitespace text');
            }
            $firstOffset = (int) $matches[0][0][1];
            $leading = substr($value, 0, $firstOffset);
            $lastEnd = $firstOffset + strlen((string) $matches[0][0][0]);
            $current = $leading . (string) $matches[0][0][0];
            $currentId = $original;
            $currentBytes = $this->encodedBytes($current);
            $separators = [];
            for ($i = 1, $count = count($matches[0]); $i < $count; ++$i) {
                $word = (string) $matches[0][$i][0];
                $offset = (int) $matches[0][$i][1];
                $separator = substr($value, $lastEnd, $offset - $lastEnd);
                $candidateBytes = $currentBytes + $this->encodedBytes($separator) - 2
                    + $this->encodedBytes($word) - 2;
                if (2 + $this->entryBytesEncoded($currentId, $candidateBytes) <= $maxBytes) {
                    $current .= $separator . $word;
                    $currentBytes = $candidateBytes;
                } else {
                    $this->appendChunk($currentId, $original, $current, $separators, $maxBytes);
                    $separators[] = $separator;
                    $current = $word;
                    $currentId = $this->nextId($nextId, $used);
                    $currentBytes = $this->encodedBytes($word);
                }
                $lastEnd = $offset + strlen($word);
            }
            $suffix = substr($value, $lastEnd);
            $fullBytes = $currentBytes + $this->encodedBytes($suffix) - 2;
            if (2 + $this->entryBytesEncoded($currentId, $fullBytes) <= $maxBytes) {
                $current .= $suffix;
                $currentBytes = $fullBytes;
                $suffix = '';
            }
            $this->trailing[$original] = $suffix;
            $this->appendChunk($currentId, $original, $current, $separators, $maxBytes);
        }

        $packet = [];
        $packetBytes = 2;
        foreach ($this->chunks as $chunk) {
            $entry = $this->entryBytes($chunk['id'], $chunk['text']);
            $wouldBytes = count($packet) === 0 ? 2 + $entry : $packetBytes + 1 + $entry;
            if (
                count($packet) > 0
                && (($maxSegments !== null && count($packet) >= $maxSegments) || $wouldBytes > $maxBytes)
            ) {
                $this->payloads[] = $this->encode($packet);
                $packet = [];
                $packetBytes = 2;
                $wouldBytes = 2 + $entry;
            }
            if ($wouldBytes > $maxBytes) {
                throw new \InvalidArgumentException('Segment value cannot fit within the byte cap');
            }
            $packet[$chunk['id']] = $chunk['text'];
            $packetBytes = $wouldBytes;
        }
        if (count($packet) > 0) {
            $this->payloads[] = $this->encode($packet);
        }
    }

    /** @return list<string> */
    public function getPayloads(): array
    {
        return $this->payloads;
    }

    public function restore(string $translatedJson): string
    {
        $expected = array_map(static fn(array $chunk): string => $chunk['id'], $this->chunks);
        $values = SegmentTranslations::decode($translatedJson, $expected);
        $restored = [];
        foreach ($this->originals as $original => $_source) {
            $restored[$original] = '';
        }
        foreach ($this->chunks as $chunk) {
            $restored[$chunk['original']] .= $chunk['separator'] . $values[$chunk['id']];
        }
        foreach ($this->trailing as $original => $suffix) {
            $restored[$original] .= $suffix;
        }
        return $this->encode($restored);
    }

    /** @param array<string,bool> $used */
    private function nextId(int &$next, array &$used): string
    {
        while (isset($used['s' . $next])) {
            if ($next === PHP_INT_MAX) {
                throw new \InvalidArgumentException('No available generated segment id');
            }
            ++$next;
        }
        $id = 's' . $next;
        $used[$id] = true;
        if ($next < PHP_INT_MAX) {
            ++$next;
        }
        return $id;
    }

    private function entryBytes(string $id, string $value): int
    {
        return $this->entryBytesEncoded($id, $this->encodedBytes($value));
    }

    private function entryBytesEncoded(string $id, int $encodedValueBytes): int
    {
        return strlen(json_encode($id, JSON_THROW_ON_ERROR)) + 1 + $encodedValueBytes;
    }

    private function encodedBytes(string $value): int
    {
        return strlen(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param list<string> $separators */
    private function appendChunk(
        string $id,
        string $original,
        string $text,
        array $separators,
        int $maxBytes
    ): void {
        if (2 + $this->entryBytes($id, $text) > $maxBytes) {
            throw new \InvalidArgumentException('Segment value cannot fit within the byte cap');
        }
        $this->chunks[] = [
            'id' => $id,
            'original' => $original,
            'text' => $text,
            'separator' => $separators === [] ? '' : (string) end($separators),
        ];
    }

    /** @param array<string,string> $values */
    private function encode(array $values): string
    {
        return json_encode((object) $values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
