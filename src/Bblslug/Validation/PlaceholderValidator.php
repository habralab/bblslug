<?php

declare(strict_types=1);

namespace Bblslug\Validation;

/** Verify only the markers visible in the fully prepared input, before restoration. */
class PlaceholderValidator implements ValidatorInterface
{
    /** @var array<string,int> */
    private array $expected;

    public function __construct(string $prepared)
    {
        $this->expected = self::countMarkers($prepared);
    }

    public function validate(string $content): ValidationResult
    {
        $actual = self::countMarkers($content);
        $errors = [];
        foreach ($this->expected as $marker => $count) {
            $found = $actual[$marker] ?? 0;
            if ($found !== $count) {
                $errors[] = sprintf('%s: expected %d occurrence(s), found %d', $marker, $count, $found);
            }
        }
        foreach ($actual as $marker => $count) {
            if (!isset($this->expected[$marker])) {
                $errors[] = sprintf('%s: unexpected marker (%d occurrence(s))', $marker, $count);
            }
        }
        return $errors === [] ? ValidationResult::success() : ValidationResult::failure($errors);
    }

    /** @return array<string,int> */
    private static function countMarkers(string $text): array
    {
        preg_match_all('/@@[0-9]+@@/', $text, $matches);
        $counts = [];
        foreach ($matches[0] as $marker) {
            $counts[$marker] = ($counts[$marker] ?? 0) + 1;
        }
        return $counts;
    }
}
