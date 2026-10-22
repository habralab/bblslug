<?php

declare(strict_types=1);

namespace Bblslug\Filters;

use Bblslug\Filters\PlaceholderCounter;

interface FilterInterface
{
    public function apply(string $text, PlaceholderCounter $counter): string;
    public function restore(string $text): string;

    /**
     * Return per-filter stats in a stable shape.
     *
     * @return array{filter:string,count:int}
     */
    public function getStats(): array;
}
