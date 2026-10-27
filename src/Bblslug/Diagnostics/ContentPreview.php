<?php

declare(strict_types=1);

namespace Bblslug\Diagnostics;

use Bblslug\SecretMasker;

/** @internal Bounded content diagnostics that mask complete secrets before truncation. */
final class ContentPreview
{
    public static function render(string $content, SecretMasker $masker, int $limit): string
    {
        $masked = $masker->redact($content);
        return mb_strlen($masked) > $limit ? mb_substr($masked, 0, $limit) . '…' : $masked;
    }
}
