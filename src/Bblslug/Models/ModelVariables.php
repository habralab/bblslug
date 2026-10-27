<?php

declare(strict_types=1);

namespace Bblslug\Models;

/** @internal Keep facade controls and request-local state out of model overrides. */
final class ModelVariables
{
    private const RESERVED = [
        'format', 'dryRun', 'promptKey', 'verbose', 'context', 'responseContext', 'operationDeadline',
        'segmentAbbreviations',
    ];

    /** @param array<string,mixed> $variables */
    public static function validate(array $variables): void
    {
        foreach (self::RESERVED as $name) {
            if (array_key_exists($name, $variables)) {
                throw new \InvalidArgumentException('Reserved option in variables: ' . $name);
            }
        }
    }
}
