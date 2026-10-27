<?php

declare(strict_types=1);

namespace Bblslug\Console;

/** @internal Pure readers for getopt value shapes; CLI policy and presentation stay at the call site. */
final class OptionValues
{
    /** @param array<string,mixed> $options Parsed getopt values; omitted options stay null. */
    public static function positiveInteger(array $options, string $name): ?int
    {
        if (!isset($options[$name])) {
            return null;
        }
        $raw = $options[$name];
        if (is_array($raw)) {
            throw new \InvalidArgumentException("--{$name} may only be specified once.");
        }
        if (!is_string($raw) || !preg_match('/\A[1-9][0-9]*\z/', $raw)) {
            throw new \InvalidArgumentException("--{$name} requires a positive integer value.");
        }
        $maxInt = (string) PHP_INT_MAX;
        if (
            strlen($raw) > strlen($maxInt)
            || (strlen($raw) === strlen($maxInt) && strcmp($raw, $maxInt) > 0)
        ) {
            throw new \InvalidArgumentException("--{$name} is too large.");
        }
        return (int) $raw;
    }

    /**
     * Preserve repeated strings and separate empty arguments, including root JSON Pointers.
     *
     * @param array<string,mixed> $options
     * @return list<string>
     */
    public static function strings(array $options, string $name, string $errorMessage): array
    {
        if (!isset($options[$name])) {
            return [];
        }
        $raw = $options[$name];
        $values = is_array($raw) ? $raw : [$raw];
        $strings = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException($errorMessage);
            }
            $strings[] = $value;
        }
        return $strings;
    }

    /**
     * Read comma-separated overrides without applying reserved-name or provider policy.
     *
     * @param array<string,mixed> $options
     * @return array<string,string>
     */
    public static function variables(array $options): array
    {
        $variables = [];
        if (isset($options['variables']) && is_string($options['variables']) && $options['variables'] !== '') {
            foreach (explode(',', $options['variables']) as $pair) {
                $pair = trim($pair);
                if ($pair == '') {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $pair, 2)) + [null, null];
                if ($k !== null && $v !== null) {
                    $variables[$k] = $v;
                } else {
                    throw new \InvalidArgumentException("Invalid --variables format: '{$pair}'");
                }
            }
        }

        return $variables;
    }
}
