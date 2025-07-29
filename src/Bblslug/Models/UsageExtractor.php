<?php

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
     * @return array<string,mixed>                   Normalized usage metrics (empty if unsupported or no data).
     */
    public static function extract(array $modelConfig, ?array $rawUsage): array
    {
        $map = $modelConfig['usage'] ?? [];
        if (!$map || $rawUsage === null) {
            return [];
        }

        $result = [];

        foreach ($map as $category => $spec) {
            // e.g. $category = 'tokens', $spec = [ 'total' => 'usage.total_tokens', 'breakdown' => […] ]
            $entry = [];

            // extract total if present
            if (isset($spec['total'])) {
                $entry['total'] = self::getInt($rawUsage, $spec['total']);
            }

            // extract breakdown if present
            if (!empty($spec['breakdown']) && is_array($spec['breakdown'])) {
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
     * its integer value (or 0 if missing/not numeric).
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

        if (is_numeric($cursor)) {
            return (int)$cursor;
        }

        if (is_string($cursor) && ctype_digit($cursor)) {
            return (int)$cursor;
        }

        return 0;
    }
}
