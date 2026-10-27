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

    /** @var array<string,true> */
    private array $abbreviations = [];

    /** @param array<mixed> $abbreviations */
    public function __construct(string $payload, ?int $maxSegments, int $maxBytes, array $abbreviations = [])
    {
        if (!array_is_list($abbreviations)) {
            throw new \InvalidArgumentException('Segment abbreviations must be a list of tokens');
        }
        foreach ($abbreviations as $abbreviation) {
            if (
                !is_string($abbreviation) || str_contains($abbreviation, "\0")
                || preg_match('/\A\S+\z/u', $abbreviation) !== 1
            ) {
                throw new \InvalidArgumentException('Segment abbreviations require nonempty UTF-8 tokens');
            }
            $key = $this->abbreviationKey($abbreviation);
            if (preg_match('/\A\S+\.\z/u', $key) !== 1) {
                throw new \InvalidArgumentException('Segment abbreviations must end with a period');
            }
            $this->abbreviations[$key] = true;
        }
        if ($maxSegments !== null && $maxSegments < 1) {
            throw new \InvalidArgumentException('Segment count cap must be positive');
        }
        if ($maxBytes < 1) {
            throw new \InvalidArgumentException('Segment byte cap must be positive');
        }

        $values = SegmentTranslations::decodeSource($payload, requireBoundedIds: true);
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
            $oversized = 2 + $this->entryBytes($original, $value) > $maxBytes;
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
            $keptThrough = $this->abbreviationEnd($matches[0], 0);
            for ($i = 1, $count = count($matches[0]); $i < $count; ++$i) {
                $word = (string) $matches[0][$i][0];
                $offset = (int) $matches[0][$i][1];
                $separator = substr($value, $lastEnd, $offset - $lastEnd);
                $candidateBytes = $currentBytes + $this->encodedBytes($separator) - 2
                    + $this->encodedBytes($word) - 2;
                $moveAbbreviation = false;
                if ($this->abbreviations !== [] && $i > $keptThrough) {
                    $keptThrough = $this->abbreviationEnd($matches[0], $i);
                    if ($keptThrough > $i) {
                        $end = (int) $matches[0][$keptThrough][1]
                            + strlen((string) $matches[0][$keptThrough][0]);
                        $keptBytes = $this->encodedBytes(substr($value, $offset, $end - $offset));
                        $withKept = $currentBytes + $this->encodedBytes($separator) - 2 + $keptBytes - 2;
                        // Move the run before it reaches the cap, keeping the abbreviation
                        // with its following token whenever the run fits a fresh field.
                        $moveAbbreviation = 2 + $this->entryBytesEncoded($currentId, $withKept) > $maxBytes
                            && 2 + $this->entryBytesEncoded('s' . $nextId, $keptBytes) <= $maxBytes;
                    }
                }
                // Keep sentence-sized units in oversized values: a provider must
                // return each unit rather than condensing repeated prose in one string.
                $sentenceEnd = $oversized && $this->isSentenceEnd(
                    (string) $matches[0][$i - 1][0],
                    $word,
                    $current
                );
                if (
                    !$sentenceEnd && !$moveAbbreviation
                    && 2 + $this->entryBytesEncoded($currentId, $candidateBytes) <= $maxBytes
                ) {
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

    private function abbreviationKey(string $token): string
    {
        $bare = preg_replace('/\A["\x{27}«“‘(\[]+|["\x{27}»”’)\]]+\z/u', '', $token);
        return mb_strtolower($bare ?? $token);
    }

    private function isAbbreviation(string $token): bool
    {
        return $this->abbreviations !== [] && isset($this->abbreviations[$this->abbreviationKey($token)]);
    }

    /** @param list<array{string,int}> $words */
    private function abbreviationEnd(array $words, int $start): int
    {
        $end = $start;
        $count = count($words);
        while ($end + 1 < $count && $this->isAbbreviation($words[$end][0])) {
            ++$end;
        }
        return $end;
    }

    private function isSentenceEnd(string $previous, string $next, string $current): bool
    {
        if (preg_match('/([.!?。！？])["\x{27}»”’)\]]*\z/u', $previous, $ending) !== 1) {
            return false;
        }
        if ($ending[1] !== '.') {
            return true;
        }
        if ($this->isAbbreviation($previous)) {
            return false;
        }
        // Dotted initials stay with their context; a hard byte cap can still split them.
        if (preg_match('/\A["\x{27}«“‘(\[]*(?:\p{L}\.)+\z/u', $previous) === 1) {
            return false;
        }
        // A leading list number belongs to its item, not to an independent value.
        if (preg_match('/\A\s*\p{Nd}+\.\z/u', $current) === 1) {
            return false;
        }
        // Lowercase continuation covers abbreviations and nonterminal ellipses.
        return preg_match('/\A["\x{27}«“‘(\[]*\p{Ll}/u', $next) !== 1;
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
