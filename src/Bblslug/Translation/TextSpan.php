<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** @internal Byte range of a translatable value in an unchanged text source. */
final readonly class TextSpan
{
    public function __construct(public int $start, public int $length)
    {
    }
}
