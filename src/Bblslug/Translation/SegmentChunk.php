<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** @internal One planned translation unit and its original reconstruction boundary. */
final readonly class SegmentChunk
{
    public function __construct(
        public string $id,
        public string $original,
        public string $text,
        public string $separator
    ) {
    }
}
