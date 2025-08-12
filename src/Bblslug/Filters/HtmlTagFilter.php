<?php

declare(strict_types=1);

namespace Bblslug\Filters;

use Bblslug\Filters\FilterInterface;
use Bblslug\Filters\PlaceholderCounter;

class HtmlTagFilter implements FilterInterface
{
    private string $tag;

    /** @var array<string,string> placeholder => original */
    private array $map = [];

    public function __construct(string $tag)
    {
        $this->tag = $tag;
    }

    public function apply(string $text, PlaceholderCounter $counter): string
    {
        $pattern = sprintf('/<%s.*?>.*?<\/%s>/is', $this->tag, $this->tag);
        $replaced = preg_replace_callback(
            $pattern,
            /** @param array<int,string> $m */
            function (array $m) use ($counter): string {
                $ph = $counter->next();
                $this->map[$ph] = $m[0];
                return $ph;
            },
            $text
        );
        // preg_replace_callback may return null on error; fall back to original text
        return $replaced !== null ? $replaced : $text;
    }

    public function restore(string $text): string
    {
        return str_replace(array_keys($this->map), array_values($this->map), $text);
    }

    /**
     * @return array{filter:string,count:int}
     */
    public function getStats(): array
    {
        return ['filter' => "html_{$this->tag}", 'count' => count($this->map)];
    }
}
