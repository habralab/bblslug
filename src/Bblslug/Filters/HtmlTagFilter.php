<?php

namespace Bblslug\Filters;

use Bblslug\Filters\FilterInterface;
use Bblslug\Filters\PlaceholderCounter;

class HtmlTagFilter implements FilterInterface
{
    private string $tag;
    private array $map = [];

    public function __construct(string $tag)
    {
        $this->tag = $tag;
    }

    public function apply(string $text, PlaceholderCounter $counter): string
    {
        $pattern = sprintf('/<%s.*?>.*?<\/%s>/is', $this->tag, $this->tag);
        return preg_replace_callback(
            $pattern,
            function ($m) use (&$counter) {
                $ph = $counter->next();
                $this->map[$ph] = $m[0];
                return $ph;
            },
            $text
        );
    }

    public function restore(string $text): string
    {
        return str_replace(array_keys($this->map), array_values($this->map), $text);
    }

    public function getStats(): array
    {
        return ['filter' => "html_{$this->tag}", 'count' => count($this->map)];
    }
}
