<?php

declare(strict_types=1);

namespace Bblslug\Validation;

/** Compare non-string scalar tokens after JSON syntax and structure validation. */
class JsonScalarValidator implements ValidatorInterface
{
    /** @var array<int,string> */
    private array $expected;

    public function __construct(string $source)
    {
        $this->expected = self::scalarTokens($source);
    }

    public function validate(string $content): ValidationResult
    {
        if ($this->expected === self::scalarTokens($content)) {
            return ValidationResult::success();
        }
        return ValidationResult::failure([
            'Non-string JSON values must retain their original JSON representation',
        ]);
    }

    /** @return array<int,string> */
    private static function scalarTokens(string $json): array
    {
        // Skip whole strings, including escaped quotes. Keep numbers as text to avoid rounding.
        $pattern = <<<'REGEX'
~"(?:[^"\\]++|\\.)*+"(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?|true|false|null~s
REGEX;
        if (preg_match_all($pattern, $json, $matches) === false) {
            throw new \RuntimeException('Could not inspect JSON scalar values');
        }
        return $matches[0];
    }
}
