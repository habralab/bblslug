<?php

declare(strict_types=1);

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
     * Feature flags for small, focused JSON "repairs".
     * More flags can be added alongside REPAIR_MISSING_NULLS.
     */
    public const REPAIR_MISSING_NULLS = 'repair_missing_nulls';

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
     * Apply selected micro-repairs to the `$after` value, using `$before` as reference.
     * Repairs are opt-in via feature flags to allow multiple independent fix-ups.
     *
     * @param mixed $before   Source value before translation (reference structure)
     * @param mixed $after    Value after translation (to be repaired)
     * @param array $features List of feature flags (Schema::REPAIR_*)
     * @return mixed          Repaired $after
     */
    public static function applyRepairs(mixed $before, mixed $after, array $features = []): mixed
    {
        if (empty($features)) {
            return $after;
        }
        foreach ($features as $flag) {
            switch ($flag) {
                case self::REPAIR_MISSING_NULLS:
                    $after = self::repairMissingNulls($before, $after);
                    break;
                default:
                    // Unknown/disabled flag: no-op for forward compatibility.
                    break;
            }
        }
        return $after;
    }

    /**
     * Repair: restore keys/elements that existed in $before with value null,
     * but are missing in $after (common LLM "cleanup" behavior).
     *
     * - For associative arrays (objects): if key is missing in $after and $before[key] === null, add key => null.
     * - For lists (indexed arrays): if index is missing in $after and $before[index] === null, add null at that index.
     * - Recurses into existing branches.
     *
     * @param mixed $before
     * @param mixed $after
     * @return mixed
     */
    private static function repairMissingNulls(mixed $before, mixed $after): mixed
    {
        if (!is_array($before)) {
            // Scalars/objects that are not arrays: nothing to repair
            return $after;
        }

        $isList = array_is_list($before);

        if ($isList) {
            // Ensure $after is an array to allow index restoration
            $out = is_array($after) ? $after : [];
            $max = max(count($before), count($out));
            for ($i = 0; $i < $max; $i++) {
                $bHas = array_key_exists($i, $before);
                $aHas = array_key_exists($i, $out);

                if ($bHas && !$aHas) {
                    if ($before[$i] === null) {
                        $out[$i] = null;
                    }
                    // if $before[$i] !== null and missing in $after -> do not invent values
                    continue;
                }
                if ($bHas && $aHas) {
                    $out[$i] = self::repairMissingNulls($before[$i], $out[$i]);
                }
            }
            // Preserve list semantics
            ksort($out);
            return $out;
        }

        // Associative array (object-like)
        $out = is_array($after) ? $after : [];
        foreach ($before as $k => $v) {
            if (!array_key_exists($k, $out)) {
                if ($v === null) {
                    $out[$k] = null;
                }
                // if $v !== null and key is missing -> do not create a value
                continue;
            }
            $out[$k] = self::repairMissingNulls($v, $out[$k]);
        }
        return $out;
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
