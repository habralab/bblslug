<?php

declare(strict_types=1);

namespace Bblslug\Validation;

/**
 * DTO for syntax validation results.
 */
class ValidationResult
{
    private bool $valid;

    /** @var array<int,string> */
    private array $errors;

    /**
     * @param array<int,string> $errors
     */
    public function __construct(bool $valid, array $errors = [])
    {
        $this->valid = $valid;
        $this->errors = $errors;
    }

    /**
     * @return bool True if content is valid
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return string[] List of validation error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create a successful result (no errors)
     */
    public static function success(): self
    {
        return new self(true, []);
    }

    /**
     * Create a failure result with given errors
     *
     * @param array<int,string> $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, $errors);
    }
}
