<?php

namespace Babelium\Filters;

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

interface FilterInterface
{
    public function apply(string $text, PlaceholderCounter $counter): string;
    public function restore(string $text): string;
    public function getStats(): array;
}

class UrlFilter implements FilterInterface
{
    private array $map = [];

    public function apply(string $text, PlaceholderCounter $counter): string
    {
        return preg_replace_callback(
            '/\b(?:https?|ftp|mailto):\/\/[^\s"<>()]+/i',
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
        return ['filter' => 'url', 'count' => count($this->map)];
    }
}

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
