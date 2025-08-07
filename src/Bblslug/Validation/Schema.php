<?php

namespace Bblslug\Validation;

use Bblslug\Validation\ValidationResult;

/**
 * General-purpose schema utility for container validation.
 *
 * Provides methods to capture a simplified schema from PHP data
 * and to compare two such schemas.
 */
class Schema
{
    /**
     * Recursively build a simplified schema tree from PHP data.
     * - Scalars map to their gettype()
     * - Indexed arrays become ordered lists of schemas
     * - Associative arrays become key=>schema maps
     *
     * @param mixed $data Input PHP value
     * @return mixed      Schema tree
     */
    public static function capture(mixed $data): mixed
    {
        if (is_array($data)) {
            $isList = array_is_list($data);
            $out = [];
            foreach ($data as $key => $value) {
                $out[$isList ? $key : $key] = self::capture($value);
            }
            return $isList ? array_values($out) : $out;
        }
        return gettype($data);
    }

    /**
     * Compare two schema trees for strict equality.
     *
     * @param mixed $before Schema captured before translation
     * @param mixed $after  Schema captured after translation
     * @return ValidationResult
     */
    public static function validate(mixed $before, mixed $after): ValidationResult
    {
        if ($before === $after) {
            return ValidationResult::success();
        }
        return ValidationResult::failure([
            'Structure mismatch after translation',
        ]);
    }
}
