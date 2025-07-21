<?php

namespace Bblslug\Filters;

use Bblslug\Filters\FilterInterface;
use Bblslug\Filters\PlaceholderCounter;

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
