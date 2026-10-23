<?php

declare(strict_types=1);

namespace Bblslug\Translation;

use Bblslug\Validation\PlaceholderValidator;

/** Strict response protocol shared by HTML fragments and selected HTML-in-JSON. */
final class SegmentTranslations
{
    /**
     * @param list<string> $expected
     * @return array<string,string>
     */
    public static function decode(string $translatedJson, array $expected): array
    {
        try {
            $decoded = json_decode($translatedJson, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Invalid HTML segment JSON', 0, $exception);
        }
        if (!$decoded instanceof \stdClass) {
            throw new \RuntimeException('HTML segment JSON must be an object');
        }
        $values = get_object_vars($decoded);
        $actual = array_keys($values);
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            $missing = array_values(array_diff($expected, $actual));
            throw new \RuntimeException(
                'HTML segment ids mismatch: missing [' . implode(', ', array_slice($missing, 0, 10)) . ']; '
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
            $key = json_decode($token, true, 512, JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) {
                throw new \RuntimeException('Duplicate HTML segment ids');
            }
            $seen[$key] = true;
        }
        $strings = [];
        foreach ($values as $key => $value) {
            if (!is_string($value) || preg_match('/\A\s*\z/u', $value) === 1) {
                throw new \RuntimeException('HTML segments require nonempty translated strings');
            }
            if (!(new PlaceholderValidator(''))->validate($value)->isValid()) {
                throw new \RuntimeException('HTML segment translations may not introduce placeholders');
            }
            $strings[$key] = $value;
        }
        return $strings;
    }
}
