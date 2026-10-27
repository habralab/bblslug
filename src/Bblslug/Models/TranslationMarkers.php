<?php

declare(strict_types=1);

namespace Bblslug\Models;

/** @internal Shared framing for translation prompts and marker-based replies. */
final class TranslationMarkers
{
    public const START = '‹‹TRANSLATION››';
    public const END = '‹‹END››';

    public static function wrap(string $text): string
    {
        return self::START . "\n" . $text . "\n" . self::END;
    }

    /** Return the first complete pair, or null when no pair can be extracted. */
    public static function extract(string $content): ?string
    {
        $pattern = '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s';
        return preg_match($pattern, $content, $matches) ? trim($matches[1]) : null;
    }
}
