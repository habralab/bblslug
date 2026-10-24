<?php

declare(strict_types=1);

namespace Bblslug\Models;

/**
 * Class UsageExtractor
 *
 * Provides normalization of raw usage statistics from various model vendors
 * into a common “consumed” schema.
 */
class UsageExtractor
{
    /**
     * Sum normalized usage metrics across requests.
     *
     * A metric is included only when it is present in at least one input
     * entry; absent totals and breakdown categories are not invented.
     *
     * @param list<array<string, array{total?: int, breakdown?: array<string, int>}>> $usages
     * @return array<string, array{total?: int, breakdown?: array<string, int>}>
     */
    public static function sum(array $usages): array
    {
        /** @var array<string, array{total?: int, breakdown?: array<string, int>}> $result */
        $result = [];

        foreach ($usages as $usage) {
            foreach ($usage as $category => $entry) {
                if (!isset($result[$category])) {
                    $result[$category] = [];
                }

                if (array_key_exists('total', $entry)) {
                    $result[$category]['total'] = ($result[$category]['total'] ?? 0) + $entry['total'];
                }

                if (array_key_exists('breakdown', $entry)) {
                    if (!isset($result[$category]['breakdown'])) {
                        $result[$category]['breakdown'] = [];
                    }
                    foreach ($entry['breakdown'] as $name => $value) {
                        $result[$category]['breakdown'][$name] =
                            ($result[$category]['breakdown'][$name] ?? 0) + $value;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Extract and normalize usage metrics according to
     * the `usage` section in the model’s config.
     *
     * Your registry should define, for example:
     *
     *   usage:
     *     tokens:
     *       total:       usage.total_tokens
     *       breakdown:
     *         prompt:      usage.prompt_tokens
     *         completion:  usage.completion_tokens
     *
     * @param array<string,mixed>        $modelConfig Model configuration (must include 'vendor').
     * @param array<string,mixed>|null   $rawUsage    Raw usage data as returned by the driver, or null.
     * @return array<string, array{total?: int, breakdown?: array<string,int>}>
     *                   Normalized usage metrics (empty if unsupported or no data).
     */
    public static function extract(array $modelConfig, ?array $rawUsage): array
    {
        $usageMap = $modelConfig['usage'] ?? null;
        if (!is_array($usageMap) || $rawUsage === null) {
            return [];
        }

        /** @var array<string, array{total?: string, breakdown?: array<string,string>}> $usageMap */
        /** @var array<string, array{total?: int, breakdown?: array<string,int>}> $result */
        $result = [];

        foreach ($usageMap as $category => $spec) {
            // e.g. $category = 'tokens', $spec = [ 'total' => 'usage.total_tokens', 'breakdown' => […] ]

            /** @var array{total?: int, breakdown?: array<string,int>} $entry */
            $entry = [];

            // extract total if present
            if (isset($spec['total'])) {
                /** @var string $totalPath */
                $totalPath = $spec['total'];
                $entry['total'] = self::getInt($rawUsage, $totalPath);
            }

            // extract breakdown if present
            if (isset($spec['breakdown'])) {
                $bd = [];
                foreach ($spec['breakdown'] as $subKey => $path) {
                    $bd[$subKey] = self::getInt($rawUsage, $path);
                }
                $entry['breakdown'] = $bd;
            }

            $result[$category] = $entry;
        }

        return $result;
    }

    /**
     * Traverse an array by a dot-notation path and return
     * its nonnegative whole count (or 0 if missing, invalid or out of range).
     *
     * @param array<string,mixed> $data Raw usage array
     * @param string              $path Dot-notation path, e.g. 'usage.total_tokens'
     * @return int                          The resolved integer, or 0
     */
    private static function getInt(array $data, string $path): int
    {
        $parts = explode('.', $path);
        $cursor = $data;

        foreach ($parts as $p) {
            if (!is_array($cursor) || !array_key_exists($p, $cursor)) {
                return 0;
            }
            $cursor = $cursor[$p];
        }

        if (is_int($cursor)) {
            return $cursor >= 0 ? $cursor : 0;
        }
        if (is_string($cursor)) {
            $integer = trim($cursor, " \t\n\r\v\f");
            if (str_starts_with($integer, '+')) {
                $integer = substr($integer, 1);
            }
            if ($integer === '' || !ctype_digit($integer)) {
                return 0;
            }
            // Keep decimal integer strings exact, including PHP_INT_MAX, without rounding.
            $integer = ltrim($integer, '0');
            $maximum = (string) PHP_INT_MAX;
            if (
                strlen($integer) > strlen($maximum)
                || (strlen($integer) === strlen($maximum) && strcmp($integer, $maximum) > 0)
            ) {
                return 0;
            }
            return (int) $integer;
        }
        if (
            !is_float($cursor) || !is_finite($cursor) || $cursor < 0
            || $cursor >= ((float) PHP_INT_MAX + 1.0) || floor($cursor) !== $cursor
        ) {
            return 0;
        }
        return (int) $cursor;
    }
}
