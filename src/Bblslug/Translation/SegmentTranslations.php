<?php

declare(strict_types=1);

namespace Bblslug\Translation;

use Bblslug\Validation\PlaceholderValidator;

/** Strict response protocol shared by locally reconstructed text and HTML fragments. */
final class SegmentTranslations
{
    /**
     * @param list<string> $expected
     * @return array<string,string>
     */
    public static function decode(
        string $translatedJson,
        array $expected,
        string $label = 'HTML',
        bool $allowLineBreaks = true
    ): array {
        try {
            $decoded = json_decode($translatedJson, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Invalid ' . $label . ' segment JSON', 0, $exception);
        }
        if (!$decoded instanceof \stdClass) {
            throw new \RuntimeException($label . ' segment JSON must be an object');
        }
        $values = get_object_vars($decoded);
        $actual = array_keys($values);
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            $missing = array_values(array_diff($expected, $actual));
            throw new \RuntimeException(
                $label . ' segment ids mismatch: missing [' . implode(', ', array_slice($missing, 0, 10)) . ']; '
                . 'unexpected count ' . count(array_diff($actual, $expected))
            );
        }
        // Consume complete JSON strings so escaped quotes/colons in values are never keys.
        $pattern = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"~s
REGEX;
        preg_match_all($pattern, $translatedJson, $tokens, PREG_OFFSET_CAPTURE);
        $seen = [];
        foreach ($tokens[0] as [$token, $offset]) {
            if (preg_match('/\G\s*:/', $translatedJson, $unused, 0, $offset + strlen($token)) !== 1) {
                continue;
            }
            $key = self::requireStringId(json_decode($token, true, 512, JSON_THROW_ON_ERROR), $label);
            if (isset($seen[$key])) {
                throw new \RuntimeException('Duplicate ' . $label . ' segment ids');
            }
            $seen[$key] = true;
        }
        $strings = [];
        foreach ($values as $key => $value) {
            $key = self::requireStringId($key, $label);
            if (!is_string($value) || preg_match('/\A\s*\z/u', $value) === 1) {
                throw new \RuntimeException($label . ' segments require nonempty translated strings');
            }
            if (!$allowLineBreaks && strpbrk($value, "\r\n") !== false) {
                throw new \RuntimeException($label . ' segment translations may not introduce line breaks');
            }
            if (!(new PlaceholderValidator(''))->validate($value)->isValid()) {
                throw new \RuntimeException($label . ' segment translations may not introduce placeholders');
            }
            $strings[$key] = $value;
        }
        return $strings;
    }

    /**
     * Decode a source segment object, preserving source-specific diagnostics.
     *
     * @internal
     * @param bool $requireBoundedIds Require numeric suffixes usable for allocating new IDs.
     * @return array<string,string>
     */
    public static function decodeSource(string $payload, bool $requireBoundedIds = false): array
    {
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
            // PHP converts numeric JSON object keys to integer array keys.
            if (preg_match('/\As\d+\z/', (string) $key) !== 1) {
                throw new \InvalidArgumentException('Segment JSON contains an invalid segment id');
            }
            if ($requireBoundedIds) {
                $number = substr($key, 1);
                if (
                    strlen($number) > strlen((string) PHP_INT_MAX)
                    || (strlen($number) === strlen((string) PHP_INT_MAX) && strcmp($number, (string) PHP_INT_MAX) > 0)
                ) {
                    throw new \InvalidArgumentException('Segment JSON contains an out-of-range segment id');
                }
            }
        }
        try {
            /** @var array<string,string> $values */
            $values = self::decode($payload, $keys);
        } catch (\RuntimeException $exception) {
            throw new \InvalidArgumentException('Invalid segment source payload', 0, $exception);
        }
        return $values;
    }

    /** @return array<string,mixed> */
    public static function schemaFor(string $payload): array
    {
        $packet = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        if (!$packet instanceof \stdClass) {
            throw new \InvalidArgumentException('Segment schema requires an object payload');
        }
        $ids = array_keys(get_object_vars($packet));
        return [
            'type' => 'object',
            'properties' => (object) array_fill_keys($ids, ['type' => 'string', 'minLength' => 1]),
            'required' => $ids,
            'additionalProperties' => false,
        ];
    }

    private static function requireStringId(mixed $key, string $label): string
    {
        if (!is_string($key)) {
            throw new \RuntimeException($label . ' segment ids must be strings');
        }
        return $key;
    }
}
