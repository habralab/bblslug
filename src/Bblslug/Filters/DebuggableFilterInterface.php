<?php

declare(strict_types=1);

namespace Bblslug\Filters;

/** Optional access to captured source fragments for diagnostic tools. */
interface DebuggableFilterInterface extends FilterInterface
{
    /** @return array<string,string> Placeholder => captured source slice, without diagnostic masking. */
    public function debugDump(): array;
}
