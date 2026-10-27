<?php

declare(strict_types=1);

namespace Bblslug\Validation;

use Bblslug\JsonPointer;

/** Opt-in validation of source string values containing JSON objects or arrays. */
class EmbeddedJsonValidator implements ValidatorInterface
{
    private mixed $source;

    public function __construct(string $source)
    {
        $this->source = json_decode($source, false, 512, JSON_THROW_ON_ERROR);
    }

    public function validate(string $content): ValidationResult
    {
        return self::compare(
            $this->source,
            json_decode($content, false, 512, JSON_THROW_ON_ERROR),
            '$'
        );
    }

    private static function compare(mixed $source, mixed $translated, string $path): ValidationResult
    {
        if (is_string($source)) {
            $nested = json_decode($source, false);
            if (json_last_error() !== JSON_ERROR_NONE || (!is_array($nested) && !$nested instanceof \stdClass)) {
                return ValidationResult::success();
            }
            if (!is_string($translated)) {
                return self::failure($path, 'expected an encoded JSON string');
            }
            $output = json_decode($translated, false);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return self::failure($path, 'invalid JSON syntax');
            }
            if (!Schema::validate(Schema::capture($nested), Schema::capture($output))->isValid()) {
                return self::failure($path, 'structure changed');
            }
            if (!(new JsonScalarValidator($source))->validate($translated)->isValid()) {
                return self::failure($path, 'non-string values changed');
            }
            $strings = (new JsonStringContentValidator($source))->validate($translated);
            if (!$strings->isValid()) {
                return self::failure($path . ' (encoded)', implode('; ', $strings->getErrors()));
            }
            return self::compare($nested, $output, $path . ' (encoded)');
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
            $segment = JsonPointer::encodeToken((string) $key);
            $result = self::compare($child, $outputs[$key] ?? null, $path . '/' . $segment);
            if (!$result->isValid()) {
                return $result;
            }
        }
        return ValidationResult::success();
    }

    private static function failure(string $path, string $reason): ValidationResult
    {
        return ValidationResult::failure([$path . ': ' . $reason]);
    }
}
