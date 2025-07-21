<?php

namespace Bblslug\Filters;

use Bblslug\Filters\PlaceholderCounter;

interface FilterInterface
{
    public function apply(string $text, PlaceholderCounter $counter): string;
    public function restore(string $text): string;
    public function getStats(): array;
}
