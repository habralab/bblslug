<?php

namespace Bblslug\Filters;

use Bblslug\Filters\FilterInterface;
use Bblslug\Filters\HtmlTagFilter;
use Bblslug\Filters\PlaceholderCounter;
use Bblslug\Filters\UrlFilter;

class FilterManager
{
    private array $filters = [];
    private PlaceholderCounter $counter;

    public function __construct(array $filterNames)
    {
        $this->counter = new PlaceholderCounter();

        foreach ($filterNames as $name) {
            if ($name === 'url') {
                $this->filters[] = new UrlFilter();
            } elseif (str_starts_with($name, 'html_')) {
                $tag = substr($name, 5);
                $this->filters[] = new HtmlTagFilter($tag);
            }
        }
    }

    public function apply(string $text): string
    {
        foreach ($this->filters as $filter) {
            $text = $filter->apply($text, $this->counter);
        }
        return $text;
    }

    public function restore(string $text): string
    {
        foreach (array_reverse($this->filters) as $filter) {
            $text = $filter->restore($text);
        }
        return $text;
    }

    public function getStats(): array
    {
        $stats = [];
        foreach ($this->filters as $filter) {
            $stats[] = $filter->getStats();
        }
        return $stats;
    }
}
