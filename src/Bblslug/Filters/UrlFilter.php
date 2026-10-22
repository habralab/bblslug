<?php

declare(strict_types=1);

namespace Bblslug\Filters;

use Bblslug\Filters\FilterInterface;
use Bblslug\Filters\PlaceholderCounter;

class UrlFilter implements FilterInterface
{
    /** @var array<string,string> placeholder => url */
    private array $map = [];

    public function apply(string $text, PlaceholderCounter $counter): string
    {
        $replaced = preg_replace_callback(
            '/\b(?:https?|ftp|mailto):\/\/[^\s"<>()]+/i',
            /** @param array<int,string> $m */
            function (array $m) use ($counter): string {
                $ph = $counter->next();
                $this->map[$ph] = $m[0];
                return $ph;
            },
            $text
        );
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
        return ['filter' => 'url', 'count' => count($this->map)];
    }
}
