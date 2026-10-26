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
    private static function requireStringId(mixed $key, string $label): string
    {
        if (!is_string($key)) {
            throw new \RuntimeException($label . ' segment ids must be strings');
        }
        return $key;
    }
}
