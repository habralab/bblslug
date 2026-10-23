<?php

declare(strict_types=1);

namespace Bblslug\Validation;

/** Compare exact non-string values by property/index after syntax and structure validation. */
class JsonScalarValidator implements ValidatorInterface
{
    private mixed $expected;

    public function __construct(string $source)
    {
        $this->expected = self::scalarValues($source);
    }

    public function validate(string $content): ValidationResult
    {
        if ($this->expected === self::scalarValues($content)) {
            return ValidationResult::success();
        }
        return ValidationResult::failure([
            'Non-string JSON values must retain their original JSON representation',
        ]);
    }

    private static function scalarValues(string $json): mixed
    {
        // Skip whole strings, including escaped quotes. Keep numbers as text to avoid rounding.
        $pattern = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?|true|false|null~s
REGEX;
        $quoted = preg_replace_callback(
            $pattern,
            /** @param array<int,string> $match */
            static fn (array $match): string => json_encode($match[0], JSON_THROW_ON_ERROR),
            $json
        );
        if ($quoted === null) {
            throw new \RuntimeException('Could not inspect JSON scalar values');
        }
        // The second tree keeps exact tokens as strings, even beyond PHP numeric precision.
        return self::captureValues(
            json_decode($json, false, 512, JSON_THROW_ON_ERROR),
            json_decode($quoted, false, 512, JSON_THROW_ON_ERROR)
        );
    }

    private static function captureValues(mixed $value, mixed $tokens): mixed
    {
        if ($value instanceof \stdClass) {
            // Quoting scalar leaves does not change container types or property names.
            /** @var \stdClass $tokens */
            $properties = [];
            foreach (get_object_vars($value) as $key => $child) {
                $properties[$key] = self::captureValues($child, $tokens->{(string) $key});
            }
            ksort($properties, SORT_STRING);
            return ['type' => 'object', 'properties' => $properties];
        }
        if (is_array($value)) {
            /** @var array<int,mixed> $tokens */
            $items = [];
            foreach ($value as $index => $child) {
                $items[] = self::captureValues($child, $tokens[$index]);
            }
            return $items;
        }
        // String values are translatable; the schema check already protects their type.
        return is_string($value) ? null : $tokens;
    }
}
