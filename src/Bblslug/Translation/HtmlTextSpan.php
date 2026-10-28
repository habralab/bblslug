<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** @internal Decoded HTML text and its source byte range and encoding context. */
final readonly class HtmlTextSpan
{
    public function __construct(
        public int $start,
        public int $end,
        public string $value,
        public string $context
    ) {
    }
}
