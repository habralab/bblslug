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
        unset($decoded);
        return self::validateValues($translatedJson, $values, $expected, $label, $allowLineBreaks);
    }

    /**
     * @param array<string|int,mixed> $values
     * @param list<string> $expected
     * @return array<string,string>
     */
    private static function validateValues(
        string $translatedJson,
        array $values,
        array $expected,
        string $label,
        bool $allowLineBreaks
    ): array {
        self::validateIds($values, $expected, $label);
        unset($expected);
        // Consume complete JSON strings so escaped quotes/colons in values are never keys.
        $pattern = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"~s
REGEX;
        $seen = [];
        $position = 0;
        while (($matched = preg_match($pattern, $translatedJson, $match, PREG_OFFSET_CAPTURE, $position)) === 1) {
            [$token, $offset] = $match[0];
            $position = $offset + strlen($token);
            if (preg_match('/\G\s*:/', $translatedJson, $unused, 0, $position) !== 1) {
                continue;
            }
            $key = self::requireStringId(json_decode($token, true, 512, JSON_THROW_ON_ERROR), $label);
            if (isset($seen[$key])) {
                throw new \RuntimeException('Duplicate ' . $label . ' segment ids');
            }
            $seen[$key] = true;
        }
        if ($matched === false) {
            throw new \RuntimeException('Could not inspect ' . $label . ' segment ids');
        }
        unset($seen);
        return self::validateStrings($values, $label, $allowLineBreaks);
    }

    /**
     * @internal Validate against the distinct IDs supplied by a local document or plan.
     * @param array<string|int,mixed> $values
     * @param iterable<string> $expected Distinct source IDs, without materializing another key list.
     * @return array<string,string>
     */
    public static function validate(
        array $values,
        iterable $expected,
        string $label = 'HTML',
        bool $allowLineBreaks = true
    ): array {
        $count = 0;
        $missing = [];
        foreach ($expected as $id) {
            ++$count;
            if (!array_key_exists($id, $values)) {
                $missing[] = $id;
            }
        }
        if ($missing !== [] || $count !== count($values)) {
            sort($missing);
            self::idMismatch($missing, count($values) - $count + count($missing), $label);
        }
        return self::validateStrings($values, $label, $allowLineBreaks);
    }

    /**
     * @param array<string|int,mixed> $values
     * @param list<string> $expected
     */
    private static function validateIds(array $values, array $expected, string $label): void
    {
        $actual = array_keys($values);
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            $missing = array_values(array_diff($expected, $actual));
            self::idMismatch($missing, count(array_diff($actual, $expected)), $label);
        }
    }

    /** @param list<string> $missing */
    private static function idMismatch(array $missing, int $unexpected, string $label): never
    {
        throw new \RuntimeException(
            $label . ' segment ids mismatch: missing [' . implode(', ', array_slice($missing, 0, 10)) . ']; '
            . 'unexpected count ' . $unexpected
        );
    }

    /**
     * @param array<string|int,mixed> $values
     * @return array<string,string>
     */
    private static function validateStrings(array $values, string $label, bool $allowLineBreaks): array
    {
        foreach ($values as $key => $value) {
            self::requireStringId($key, $label);
            if (
                !is_string($value) || !mb_check_encoding($value, 'UTF-8')
                || preg_match('/\A\s*\z/u', $value) === 1
            ) {
                throw new \RuntimeException($label . ' segments require nonempty translated strings');
            }
            if (!$allowLineBreaks && strpbrk($value, "\r\n") !== false) {
                throw new \RuntimeException($label . ' segment translations may not introduce line breaks');
            }
            if (!(new PlaceholderValidator(''))->validate($value)->isValid()) {
                throw new \RuntimeException($label . ' segment translations may not introduce placeholders');
            }
        }
        /** @var array<string,string> $values */
        return $values;
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

        $values = get_object_vars($decoded);
        unset($decoded);
        $keys = array_keys($values);
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
            return self::validateValues($payload, $values, $keys, 'HTML', true);
        } catch (\RuntimeException $exception) {
            throw new \InvalidArgumentException('Invalid segment source payload', 0, $exception);
        }
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
