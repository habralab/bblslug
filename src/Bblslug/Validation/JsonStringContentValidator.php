<?php

declare(strict_types=1);

namespace Bblslug\Validation;

/** Detect cleared string values after JSON syntax and schema validation. */
final class JsonStringContentValidator implements ValidatorInterface
{
    private mixed $source;

    public function __construct(string $source)
    {
        $this->source = json_decode($source, false, 512, JSON_THROW_ON_ERROR);
    }

    public function validate(string $content): ValidationResult
    {
        return self::compare($this->source, json_decode($content, false, 512, JSON_THROW_ON_ERROR), '$');
    }

    private static function compare(mixed $source, mixed $translated, string $path): ValidationResult
    {
        if (is_string($source)) {
            if (
                preg_match('/\A\s*\z/u', $source) !== 1
                && (!is_string($translated) || preg_match('/\A\s*\z/u', $translated) === 1)
            ) {
                return ValidationResult::failure([$path . ': nonempty string became empty']);
            }
            return ValidationResult::success();
        }
        if ($source instanceof \stdClass) {
            $children = get_object_vars($source);
            $outputs = $translated instanceof \stdClass ? get_object_vars($translated) : [];
        } elseif (is_array($source)) {
            $children = $source;
            $outputs = is_array($translated) ? $translated : [];
        } else {
            return ValidationResult::success();
        }
        foreach ($children as $key => $child) {
            $segment = str_replace(['~', '/'], ['~0', '~1'], (string) $key);
            $result = self::compare($child, $outputs[$key] ?? null, $path . '/' . $segment);
            if (!$result->isValid()) {
                return $result;
            }
        }
        return ValidationResult::success();
    }
}
