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
