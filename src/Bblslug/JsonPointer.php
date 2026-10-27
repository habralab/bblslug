<?php

declare(strict_types=1);

namespace Bblslug;

/** @internal String-form JSON Pointer syntax and token escaping, without document traversal. */
final class JsonPointer
{
    /** @phpstan-assert-if-true string $pointer */
    public static function isValid(mixed $pointer): bool
    {
        return is_string($pointer)
            && ($pointer === '' || $pointer[0] === '/')
            && !preg_match('/~(?![01])/', $pointer);
    }

    public static function encodeToken(string $token): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $token);
    }

    public static function decodeToken(string $token): string
    {
        // Decode simultaneously: ~01 means the literal token ~1, not a slash.
        return strtr($token, ['~1' => '/', '~0' => '~']);
    }
}
