<?php

namespace Bblslug\Filters;

class PlaceholderCounter
{
    private int $index = 0;

    public function next(): string
    {
        return "@@" . ($this->index++) . "@@";
    }

    public function current(): int
    {
        return $this->index;
    }
}
