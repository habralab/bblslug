<?php

declare(strict_types=1);

namespace Bblslug\Console;

/** @internal Raw argv checks for distinctions that getopt does not preserve. */
final class RawArguments
{
    /**
     * Reject unknown flags before getopt can silently discard or abbreviate them.
     *
     * @param list<mixed> $argv Arguments including the program name.
     * @param list<string> $longOptions Long-option declarations passed to getopt.
     */
    public static function validateKnownOptions(array $argv, array $longOptions): void
    {
        $known = [];
        foreach ($longOptions as $declaration) {
            $known['--' . rtrim($declaration, ':')] = str_ends_with($declaration, ':')
                && !str_ends_with($declaration, '::');
        }
        for ($index = 1, $count = count($argv); $index < $count; ++$index) {
            $argument = $argv[$index];
            if ($argument === '--') {
                break;
            }
            if (!is_string($argument) || $argument === '-' || !str_starts_with($argument, '-')) {
                continue;
            }
            $option = explode('=', $argument, 2)[0];
            if (!isset($known[$option])) {
                throw new \InvalidArgumentException('Unknown option: ' . $option . '.');
            }
            // A required value is data even when it resembles an option name.
            if ($known[$option] && !str_contains($argument, '=')) {
                ++$index;
            }
        }
    }

    /**
     * @param list<mixed> $argv Arguments including the program name.
     * @param list<string> $longOptions Long-option declarations passed to getopt.
     * @throws \InvalidArgumentException If a checked option is missing, empty or repeated.
     */
    public static function validate(array $argv, array $longOptions): void
    {
        $batchSizeOccurrences = 0;
        $textBatchSizeOccurrences = 0;
        $segmentBatchMaxBytesOccurrences = 0;
        $operationTimeoutOccurrences = 0;
        $checkpointOccurrences = 0;
        $textTransportOccurrences = 0;
        $translatedOccurrences = 0;
        $requiredOptions = [];
        foreach ($longOptions as $longOption) {
            if (str_ends_with($longOption, ':')) {
                $requiredOptions[substr($longOption, 0, -1)] = true;
            }
        }
        $argumentCount = count($argv);
        for ($index = 1; $index < $argumentCount; $index++) {
            $argument = $argv[$index];
            if ($argument === '--') {
                break;
            }
            if (!is_string($argument)) {
                continue;
            }
            if ($argument === '--translated' || str_starts_with($argument, '--translated=')) {
                $translatedOccurrences++;
                if ($argument === '--translated=') {
                    throw new \InvalidArgumentException('--translated requires a non-empty path.');
                }
                if ($argument === '--translated') {
                    $next = $argv[$index + 1] ?? null;
                    if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                        throw new \InvalidArgumentException('--translated requires a path.');
                    }
                }
            }
            if (
                $argument === '--html-segment-batch-size'
                || str_starts_with($argument, '--html-segment-batch-size=')
            ) {
                $batchSizeOccurrences++;
            }
            if (
                $argument === '--text-segment-batch-size'
                || str_starts_with($argument, '--text-segment-batch-size=')
            ) {
                $textBatchSizeOccurrences++;
            }
            if (
                $argument === '--segment-batch-max-bytes'
                || str_starts_with($argument, '--segment-batch-max-bytes=')
            ) {
                $segmentBatchMaxBytesOccurrences++;
            }
            if (
                $argument === '--operation-timeout'
                || str_starts_with($argument, '--operation-timeout=')
            ) {
                $operationTimeoutOccurrences++;
            }
            if ($argument === '--checkpoint' || str_starts_with($argument, '--checkpoint=')) {
                $checkpointOccurrences++;
            }
            if ($argument === '--text-transport=') {
                throw new \InvalidArgumentException('--text-transport requires document or segments.');
            } elseif ($argument === '--text-transport') {
                $textTransportOccurrences++;
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    throw new \InvalidArgumentException('--text-transport requires a value.');
                }
            } elseif (str_starts_with($argument, '--text-transport=')) {
                $textTransportOccurrences++;
            }
            if ($argument === '--validate-html-json-pointer=') {
                throw new \InvalidArgumentException(
                    'Empty --validate-html-json-pointer value must be passed as a separate empty argument.'
                );
            } elseif ($argument === '--validate-html-json-pointer') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || ($next !== '' && str_starts_with($next, '--'))) {
                    throw new \InvalidArgumentException('--validate-html-json-pointer requires a value.');
                }
            } elseif ($argument === '--html-json-pointer=') {
                throw new \InvalidArgumentException(
                    'Empty --html-json-pointer value must be passed as a separate empty argument.'
                );
            } elseif ($argument === '--html-json-pointer') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || ($next !== '' && str_starts_with($next, '--'))) {
                    throw new \InvalidArgumentException('--html-json-pointer requires a value.');
                }
            } elseif ($argument === '--html-transport=') {
                throw new \InvalidArgumentException(
                    '--html-transport requires document or segments.'
                );
            } elseif ($argument === '--html-transport') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || ($next !== '' && str_starts_with($next, '--'))) {
                    throw new \InvalidArgumentException('--html-transport requires a value.');
                }
            } elseif ($argument === '--html-segment-batch-size=') {
                throw new \InvalidArgumentException('--html-segment-batch-size requires a positive integer value.');
            } elseif ($argument === '--html-segment-batch-size') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || ($next !== '' && str_starts_with($next, '--'))) {
                    throw new \InvalidArgumentException('--html-segment-batch-size requires a value.');
                }
            } elseif ($argument === '--text-segment-batch-size=') {
                throw new \InvalidArgumentException('--text-segment-batch-size requires a positive integer value.');
            } elseif ($argument === '--text-segment-batch-size') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    throw new \InvalidArgumentException('--text-segment-batch-size requires a value.');
                }
            } elseif ($argument === '--segment-abbreviation=') {
                throw new \InvalidArgumentException('--segment-abbreviation requires a non-empty token.');
            } elseif ($argument === '--segment-abbreviation') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    throw new \InvalidArgumentException('--segment-abbreviation requires a token.');
                }
            } elseif ($argument === '--segment-batch-max-bytes=') {
                throw new \InvalidArgumentException('--segment-batch-max-bytes requires a positive integer value.');
            } elseif ($argument === '--segment-batch-max-bytes') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    throw new \InvalidArgumentException('--segment-batch-max-bytes requires a value.');
                }
            } elseif ($argument === '--operation-timeout=') {
                throw new \InvalidArgumentException('--operation-timeout requires a positive integer value.');
            } elseif ($argument === '--operation-timeout') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    throw new \InvalidArgumentException('--operation-timeout requires a value.');
                }
            } elseif ($argument === '--checkpoint=') {
                throw new \InvalidArgumentException('--checkpoint requires a non-empty path.');
            } elseif ($argument === '--checkpoint') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    throw new \InvalidArgumentException('--checkpoint requires a path.');
                }
            }
            // Values of required options may themselves look like option names.
            if (str_starts_with($argument, '--') && !str_contains($argument, '=')) {
                if (isset($requiredOptions[substr($argument, 2)])) {
                    $index++;
                }
            }
        }

        if ($translatedOccurrences > 1) {
            throw new \InvalidArgumentException('--translated may only be specified once.');
        }

        if ($batchSizeOccurrences > 1) {
            throw new \InvalidArgumentException('--html-segment-batch-size may only be specified once.');
        }
        if ($textTransportOccurrences > 1) {
            throw new \InvalidArgumentException('--text-transport may only be specified once.');
        }
        if ($textBatchSizeOccurrences > 1) {
            throw new \InvalidArgumentException('--text-segment-batch-size may only be specified once.');
        }
        if ($segmentBatchMaxBytesOccurrences > 1) {
            throw new \InvalidArgumentException('--segment-batch-max-bytes may only be specified once.');
        }
        if ($operationTimeoutOccurrences > 1) {
            throw new \InvalidArgumentException('--operation-timeout may only be specified once.');
        }
        if ($checkpointOccurrences > 1) {
            throw new \InvalidArgumentException('--checkpoint may only be specified once.');
        }
    }
}
